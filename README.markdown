php-lifestream
==============

(c) 2009 Jason Frame :: 
  [jason@onehackoranother.com](mailto:jason@onehackoranother.com) :: 
  [@jaz303](http://twitter.com/jaz303)

Released under the MIT License

Synopsis
--------

php-lifestream is a small library for efficiently aggregating "life-stream" events from various third party services (although at present it only supports Twitter). It is intended to by run via `cron`.

Installation
------------

Just move `lifestream.php` to somewhere within PHP's `include_path` and load `schema.sql` into your database.

Fetching new events
-------------------

First, register all the services from which you need to pull events:

    Lifestream::register('Lifestream_Twitter', array('username' => 'foo', 'password' => 'bar'));
    
The first parameter to `Lifestream::register()` is the service's class name. The second is an optional array of configuration options.
    
Now connect to MySQL:
    
    $db = mysql_connect('localhost', 'root', 'hohomerrychristmas');
    mysql_select_db('foobar', $db);
    
Finally, instantiate a `Lifestream` instance and call `fill()`:
    
    $lifestream = new Lifestream($db);
    $lifestream->fill();

New events from all services will be fetched and inserted into the database.

Querying the lifestream
-----------------------

As above, connect to MySQL and instantiate `Lifestream`. Use the `query()` method to return a list of events:

    // Latest events across all services (30 items is the default limit)
    $events = $lifestream->query();
    
    // 20 latest tweets
    $tweets = $lifestream->query(array('major_type' => 'tweet', 'limit' => 20));
    
    // Latest events from specific single twitter account
    $tweets = $lifestream->query(array('service_class' => 'twitter', 'service_id' => 'jaz303'));
    
    // Latest tweets or barks
    $tweets_or_barks = $lifestream->query(array('major_type' => array('bark', 'tweet')));

Creating new services
---------------------

It's easy to create new service adapters by extending `Lifestream_Service`:

    class MyService extends Lifestream_Service
    {
        // $params is the array of extra params passed to Lifestream::register()
        // use init() to check/sanitise these values.
        public function init($params) {
            if (!isset($params['username'])) {
                throw new Exception('MyService requires a username');
            }
        }
      
        // returns a string uniquely identifying this service
        public function get_service_class() { return 'my_service'; }
        
        // returns a string uniquely identifying this instance of service.
        // this makes it possible to have multiple services of the same type
        // (multiple twitter accounts, for example).
        public function get_service_id() { return $this->params['username']; }
        
        // find all new events and save them to the DB
        public function fill() {
          
            // finds the latest entry for this service in the lifestream
            $latest = $this->latest();
          
            // custom logic to find all events later than $latest from your remote service
            $new_events = ...;
            
            foreach ($new_events as $new_event) {
                
                $lifestream_event = $this->build_event();
                
                // populate $lifestream_event's members:
                // event_native_id    - native unique ID from your service
                // event_major        - arbitrary string denoting "major" type of event
                // event_minor        - arbitrary string denoting "minor" type of event
                // event              - event description/content
                // event_at           - unix timestamp of event
                
                $lifestream_event->save($this->db);
            
            }
          
        }
        
    }
