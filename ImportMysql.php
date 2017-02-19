<?php
// this could take a while...
ini_set("memory_limit", "-1");
set_time_limit(0);

/*      config variables        */
// what levels will we extract?
$levels = array(
  'Barrens', 'Bryan', 'Canyon', 'Cave',
  'Chris', 'Credits', 'Desert', 'Graveyard',
  'Matt', 'Mountain', 'Ruins', 'Summit'
);

/*      state variables         */
// level index is used as a foreign key in the database
$levelIndex = 0;
// represents our db connection
$db = null;


/**
 * Opens the database connection. Assumes MySQL.
 */
function openDatabase() {
	global $db;

	$dbName = 'journey_meshes';
	$dbUser = 'username';
	$dbPass = 'password';

	/* connect by socket */
	$dbSock = '/tmp/mysql/mysql.sock';
	$dsn = "mysql:unix_socket=$dbSock;dbname=$dbName";

	/* connect by host & port */
//	$dbHost = '127.0.0.1';
//	$dbPort = '3306';
//	$dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName";

	$db = new PDO($dsn, $dbUser, $dbPass);
}

/**
 * Loop through each level's DecorationMeshInstances to process, and manage state between files
 */
function loopThroughLevels() {
	global $levels, $levelIndex;

	foreach($levels as $level) {
		$levelIndex++;
		$directory = "Level_$level/DecorationMeshInstances";

		loopThroughClasses($directory);
	}
}

/**
 * Scan the current level directory to get a list of classes
 *
 * @param $directory
 */
function loopThroughClasses($directory) {
	$classes = getDirectoryContents($directory);

	foreach($classes as $class) {
		echo "Importing $directory/$class\n";
		processClass("$directory/$class");
	}
}

/**
 * Process the files in a class directory, looking for json format files
 *
 * @param $directory
 */
function processClass($directory) {
	$instances = getDirectoryContents($directory);

	foreach($instances as $file)
		if(stristr($file, '.json'))
			processInstance("$directory/$file");
}

/**
 * This skips the first 2 lines of output (current & parent directory)
 *
 * @param $directory
 * @return array
 */
function getDirectoryContents($directory) {
	$contents = scandir($directory);
	array_shift($contents); // skip .
	array_shift($contents); // skip ..

	return $contents;
}

/**
 * Handle the read file resource
 *
 * @param $file
 */
function processInstance($filepath) {
	$instance = json_decode(file_get_contents($filepath));

	if(!$instance || empty($instance)) {
		var_dump($filepath);
		die('failed to decode json!');
	}

	importInstance($instance);
}

/**
 * Process the instance and import to database
 *
 * @param $instance
 */
function importInstance($instance) {
	global $db, $levelIndex;

	$position = explode(' ', $instance->position);
	$position_x = $position[0];
	$position_y = $position[1];
	$position_z = $position[2];

	$sql = "INSERT INTO mesh_instances (class, hash, level_id, header, "
		. "meta1, meta2, meta3, position_x, position_y, position_z, "
		. "data1, data2, flag, render, property_count) "
		. "VALUES('$instance->class', '$instance->hash', '$levelIndex', '$instance->header', "
		. "'$instance->meta1', '$instance->meta2', '$instance->meta3', $position_x, $position_y, $position_z, "
		. "'$instance->data1', '$instance->data2', '$instance->flag', '$instance->render', '$instance->propertyCount');";

	$success = $db->exec($sql);

	if(!$success) {
		var_dump($sql);
		var_dump($success);
		die('failed to add mesh_instance!');
	}

	importInstanceProperties($instance->properties, $db->lastInsertId());
}

/**
 * Add all of the instance properties
 *
 * @param $properties
 * @param $instanceId
 */
function importInstanceProperties($properties, $instanceId) {
	$properties = json_decode(json_encode($properties), true); //convert to an array for easier traversal

	if(is_array($properties))
		foreach($properties as $key => $data)
			importInstanceProperty($key, $data, $instanceId);
}

/**
 * Add a mesh_instance_properties record
 *
 * @param $key
 * @param $data
 * @param $instanceId
 */
function importInstanceProperty($key, $data, $instanceId) {
	global $db, $levelIndex;

	$data = json_decode(json_encode($data)); // convert to an object for syntactic sugar
	$sql = "INSERT INTO mesh_instance_properties (instance_id, level_id, "
	  . "prop_name, prop_flag, prop_data, prop_texture) "
	  . "VALUES('$instanceId', '$levelIndex', '$key', "
	  . "'$data->flag', '$data->data', '$data->texture');";

	$success = $db->exec($sql);

	if(!$success) {
		var_dump($sql);
		var_dump($success);
		die('ERROR!');
	}
}



openDatabase();
loopThroughLevels();