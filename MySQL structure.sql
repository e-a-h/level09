# ************************************************************
# Sequel Pro SQL dump
# Version 4529
#
# http://www.sequelpro.com/
# https://github.com/sequelpro/sequelpro
#
# Host: localhost (MySQL 5.6.34)
# Database: journey_meshes
# Generation Time: 2017-02-18 22:12:18 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

# Dump of table mesh_instance_properties
# ------------------------------------------------------------

DROP TABLE IF EXISTS `mesh_instance_properties`;

CREATE TABLE `mesh_instance_properties` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `instance_id` int(11) unsigned NOT NULL,
  `level_id` int(11) unsigned NOT NULL,
  `prop_name` varchar(16) DEFAULT NULL,
  `prop_flag` varchar(64) DEFAULT NULL,
  `prop_data` text,
  `prop_texture` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `instance_id` (`instance_id`),
  KEY `level_id` (`level_id`),
  KEY `prop_name` (`prop_name`),
  CONSTRAINT `mesh_instance_properties_ibfk_1` FOREIGN KEY (`instance_id`) REFERENCES `mesh_instances` (`id`),
  CONSTRAINT `mesh_instance_properties_ibfk_2` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table mesh_instances
# ------------------------------------------------------------

DROP TABLE IF EXISTS `mesh_instances`;

CREATE TABLE `mesh_instances` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `class` varchar(48) DEFAULT NULL,
  `hash` varchar(32) DEFAULT NULL,
  `level_id` int(11) unsigned NOT NULL,
  `header` varchar(8) DEFAULT NULL,
  `meta1` text,
  `meta2` text,
  `meta3` text,
  `position_x` text,
  `position_y` text,
  `position_z` text,
  `data1` text,
  `data2` text,
  `flag` varchar(64) DEFAULT NULL,
  `render` text,
  `property_count` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class` (`class`),
  KEY `level_id` (`level_id`),
  KEY `hash` (`hash`),
  CONSTRAINT `mesh_instances_ibfk_1` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
