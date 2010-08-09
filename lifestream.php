<?php
class Lifestream
{
    private static $services = array();
    
    public static function register($class, $params) {
        self::$services[] = array('class' => $class, 'params' => $params);
    }
    
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function fill() {
        foreach (self::$services as $service) {
            $class = $service['class'];
            $instance = new $class($this->db, $service['params']);
            $instance->fill();
        }
    }
    
    public function query($params = array()) {
        
        $text_params = array('service_class', 'service_id', 'event_native_id',
                             'event_major', 'event_minor', 'event');
        
        $conditions = array();                    
        foreach ($text_params as $tp) {
            if (isset($params[$tp])) {
                $check = $params[$tp];
                if (is_array($check)) {
                    $conditions[] = $tp . ' IN(' . array_map(array($this, 'quote_string'), $check) . ')';
                } else {
                    $conditions[] = $tp . ' = ' . $this->quote_string($check);
                }
            }
        }
        
        $sql = "SELECT * FROM lifestream";
        if (count($conditions)) $sql .= " WHERE " . implode(' AND ', $conditions);
        
        $limit = isset($params['limit']) ? abs((int) $params['limit']) : 30;
        $sql .= " ORDER BY event_at DESC LIMIT $limit";
        
        $res = mysql_query($sql, $this->db);
        $out = array();
        
        while ($evt = mysql_fetch_object($res, 'Lifestream_Event')) {
            $out[] = $evt;
        }
        
        return $out;
    }
    
    public function quote_string($str) {
        return "'" . mysql_real_escape_string($str, $this->db) . "'";
    }
}

class Lifestream_Event
{
    public $id                      = null;
    public $service_class           = null;
    public $service_id              = null;
    public $event_native_id         = null;
    public $event_major             = null;
    public $event_minor             = null;
    public $event                   = null;
    public $event_at                = null;
    
    public function save($db) {
        if ($this->id === null) {
            
            $sql = sprintf("
                INSERT INTO lifestream
                    (service_class, service_id, event_native_id, event_major,
                     event_minor, event, event_at)
                VALUES
                    ('%s', '%s', '%s', '%s', '%s', '%s', %d)
            ",  mysql_real_escape_string($this->service_class, $db),
                mysql_real_escape_string($this->service_id, $db),
                mysql_real_escape_string($this->event_native_id, $db),
                mysql_real_escape_string($this->event_major, $db),
                mysql_real_escape_string($this->event_minor, $db),
                mysql_real_escape_string($this->event, $db),
                (int) $this->event_at
            );
            
            if (mysql_query($sql, $db)) {
                $this->id = mysql_insert_id($db);
                return true;
            } else {
                return false;
            }
            
        } else {
            throw new Exception("can't save event - already saved");
        }
    }
}

abstract class Lifestream_Service
{
    protected $db;
    protected $params;
    
    public function __construct($db, $params = array()) {
        $this->db = $db;
        $this->params = $params;
        $this->init($params);
    }
    
    public function init($params) {}
    
    public abstract function get_service_class();
    public abstract function get_service_id();
    
    public abstract function fill();
    
    public function latest() {
        
        $sql = sprintf("
            SELECT * FROM lifestream
            WHERE service_class = '%s' AND service_id = '%s'
            ORDER BY event_at DESC LIMIT 1
        ",  mysql_real_escape_string($this->get_service_class(), $this->db),
            mysql_real_escape_string($this->get_service_id(), $this->db)
        );
        
        return mysql_fetch_object(mysql_query($sql, $this->db), 'Lifestream_Event');
    
    }
    
    protected function build_event() {
        $evt = new Lifestream_Event;
        $evt->service_class = $this->get_service_class();
        $evt->service_id = $this->get_service_id();
        return $evt;
    }
}

class Lifestream_Twitter extends Lifestream_Service
{
    public function get_service_class() { return 'twitter'; }
    public function get_service_id() { return $this->params['username']; }
    
    public function fill() {
        
        $base_url  = 'http://api.twitter.com/1/statuses/user_timeline/' . $this->params['username'] . '.json';
        $base_url .= '?count=100';
        
        if ($latest = $this->latest()) {
            $base_url .= '&since_id=' . $latest->event_native_id;
            $page = 1;
            do {
                $count = $this->fill_by_query($base_url . '&page=' . $page);
                $page++;
            } while ($count > 0);
        } else {
            $this->fill_by_query($base_url);
        }
        
    }
    
    private function fill_by_query($url) {
        $count = 0;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (isset($this->params['username']) && isset($this->params['password'])) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->params['username']}:{$this->params['password']}");
        }
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result !== false) {
            foreach (json_decode($result) as $tweet) {
                $event = $this->build_event();
                $event->event_native_id = $tweet->id;
                $event->event_major = 'tweet';
                $event->event = html_entity_decode($tweet->text);
                $event->event_at = strtotime($tweet->created_at);
                if ($event->save($this->db)) {
                    $count++;
                }
            }
        }
        return $count;
    }
}
?>