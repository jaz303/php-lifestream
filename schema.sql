CREATE TABLE IF NOT EXISTS `lifestream` (
  `id` int(11) NOT NULL auto_increment,
  `service_class` varchar(255) NOT NULL,
  `service_id` varchar(255) NOT NULL,
  `event_native_id` varchar(255) NOT NULL,
  `event_major` varchar(255) NOT NULL,
  `event_minor` varchar(255) NOT NULL,
  `event` varchar(255) NOT NULL,
  `event_at` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `service_class` (`service_class`,`service_id`,`event_native_id`),
  KEY `event_at` (`event_at`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;