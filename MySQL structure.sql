# ************************************************************
# Sequel Pro SQL dump
# Version 4529
#
# http://www.sequelpro.com/
# https://github.com/sequelpro/sequelpro
#
# Host: localhost (MySQL 5.6.35)
# Database: journey_meshes
# Generation Time: 2017-06-30 05:09:38 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table levels
# ------------------------------------------------------------

DROP TABLE IF EXISTS `levels`;

CREATE TABLE `levels` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `level` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

LOCK TABLES `levels` WRITE;
/*!40000 ALTER TABLE `levels` DISABLE KEYS */;

INSERT INTO `levels` (`id`, `level`)
VALUES
  (1,'Barrens'),
  (2,'Bryan'),
  (3,'Canyon'),
  (4,'Cave'),
  (5,'Chris'),
  (6,'Credits'),
  (7,'Desert'),
  (8,'Graveyard'),
  (9,'Matt'),
  (10,'Mountain'),
  (11,'Ruins'),
  (12,'Summit');

/*!40000 ALTER TABLE `levels` ENABLE KEYS */;
UNLOCK TABLES;


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
  `normal_x` float DEFAULT NULL,
  `normal_y` float DEFAULT NULL,
  `normal_z` float DEFAULT NULL,
  `tangent_x` float DEFAULT NULL,
  `tangent_y` float DEFAULT NULL,
  `tangent_z` float DEFAULT NULL,
  `bitangent_x` float DEFAULT NULL,
  `bitangent_y` float DEFAULT NULL,
  `bitangent_z` float DEFAULT NULL,
  `position_x` float DEFAULT NULL,
  `position_y` float DEFAULT NULL,
  `position_z` float DEFAULT NULL,
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
