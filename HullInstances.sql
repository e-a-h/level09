# HullInstances tables and relations

DROP TABLE IF EXISTS `hull_faces`;
DROP TABLE IF EXISTS `hull_polydata`;
DROP TABLE IF EXISTS `hull_edges`;
DROP TABLE IF EXISTS `hull_vertices`;
DROP TABLE IF EXISTS `hull_instance`;

CREATE TABLE `hull_instance` (
  `hullinstance_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `level_id` INT(11) UNSIGNED NOT NULL DEFAULT '0',
  `uid` VARCHAR(32) NULL DEFAULT NULL,
  `face_count` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `index_count` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `edge_count` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `vertex_count` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `float_vector_a` TEXT NOT NULL,
  `float_vector_b` TEXT NOT NULL,
  `float_vector_c` TEXT NOT NULL,
  `float_vector_d` TEXT NOT NULL,
  `prop_a` INT(11) NOT NULL DEFAULT '0',
  `prop_b` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `prop_c` FLOAT NOT NULL DEFAULT '0',
  PRIMARY KEY (`hullinstance_id`),
  UNIQUE INDEX `meshinstance_id` (`hullinstance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `hull_faces` (
  `faceid` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `level_id` INT(11) UNSIGNED NOT NULL,
  `hullinstance_id` INT(11) UNSIGNED NOT NULL,
  `face_data` TEXT NOT NULL,
  PRIMARY KEY (`faceid`),
  UNIQUE INDEX `faceid` (`faceid`),
  INDEX `level_id` (`level_id`),
  INDEX `hullinstance_id` (`hullinstance_id`),
  CONSTRAINT `FK_hull_faces_hullinstance` FOREIGN KEY (`hullinstance_id`) REFERENCES `hull_instance` (`hullinstance_id`),
  CONSTRAINT `FK_hull_faces_levels` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `hull_polydata` (
  `polydataid` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `level_id` INT(11) UNSIGNED NOT NULL,
  `hullinstance_id` INT(11) UNSIGNED NOT NULL,
  `poly_data` TEXT NOT NULL,
  PRIMARY KEY (`polydataid`),
  UNIQUE INDEX `polydataid` (`polydataid`),
  INDEX `level_id` (`level_id`),
  INDEX `hullinstance_id` (`hullinstance_id`),
  CONSTRAINT `FK_hull_polydata_hullinstance` FOREIGN KEY (`hullinstance_id`) REFERENCES `hull_instance` (`hullinstance_id`),
  CONSTRAINT `FK_hull_polydata_levels` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `hull_edges` (
  `edgeid` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `level_id` INT(11) UNSIGNED NOT NULL,
  `hullinstance_id` INT(11) UNSIGNED NOT NULL,
  `edge_data` TEXT NOT NULL,
  PRIMARY KEY (`edgeid`),
  UNIQUE INDEX `edgeid` (`edgeid`),
  INDEX `level_id` (`level_id`),
  INDEX `hullinstance_id` (`hullinstance_id`),
  CONSTRAINT `FK_hull_edges_hullinstance` FOREIGN KEY (`hullinstance_id`) REFERENCES `hull_instance` (`hullinstance_id`),
  CONSTRAINT `FK_hull_edge_levels` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `hull_vertices` (
  `vertexid` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `level_id` INT(11) UNSIGNED NOT NULL,
  `hullinstance_id` INT(11) UNSIGNED NOT NULL,
  `index` MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
  `x` FLOAT NOT NULL DEFAULT '0',
  `y` FLOAT NOT NULL DEFAULT '0',
  `z` FLOAT NOT NULL DEFAULT '0',
  PRIMARY KEY (`vertexid`),
  UNIQUE INDEX `vertexid` (`vertexid`),
  INDEX `level_id` (`level_id`),
  INDEX `hullinstance_id` (`hullinstance_id`),
  CONSTRAINT `FK_hull_vertices_hullinstance` FOREIGN KEY (`hullinstance_id`) REFERENCES `hull_instance` (`hullinstance_id`),
  CONSTRAINT `FK_hull_vertices_levels` FOREIGN KEY (`level_id`) REFERENCES `levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
