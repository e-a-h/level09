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
