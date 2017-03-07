<?php
require_once 'dbHandler.php';

// this could take a while...
ini_set("memory_limit", "-1");
set_time_limit(0);
Helper::helpMe( array( Helper::HelpMultiLevel ) );
$instance_buffer = array();
$property_buffer = array();

/*      state variables         */
// level index is used as a foreign key in the database
$levelIndex = 0;
$instanceId = 1;
// represents our db connection
$db = null;

/**
 * Opens the database connection.
 */
function openDatabase()
{
	global $db, $instanceId;
	$db = dbHandler::connectByHost();
	// $db = dbHandler::connectBySocket();
	$instanceId = dbHandler::initAutoIncrement( $db, 'mesh_instances' );
}

/**
 * Loop through each level's DecorationMeshInstances to process, and manage state between files
 */
function loopThroughLevels()
{
	global $levelIndex;
	$levels = Helper::filterLevels();

	foreach( $levels as $level )
	{
		$levelIndex++;
		$directory = "Level_$level/DecorationMeshInstances";
		loopThroughClasses( $directory );
	}
}

/**
 * Scan the current level directory to get a list of classes
 *
 * @param $directory
 */
function loopThroughClasses( $directory )
{
	$classes = getDirectoryContents( $directory );

	foreach( $classes as $class )
	{
		print "Importing $directory/$class\n";
		processClass( "$directory/$class" );
	}

}

/**
 * Process the files in a class directory, looking for json format files
 *
 * @param $directory
 */
function processClass( $directory )
{
	$instances = getDirectoryContents( $directory );
	foreach( $instances as $file )
	{
		if ( stristr( $file, '.json' ) )
		{
			processInstance( "$directory/$file" );
		}
	}
}

/**
 * This skips the first 2 lines of output (current & parent directory)
 *
 * @param $directory
 * @return array
 */
function getDirectoryContents( $directory )
{
	$contents = scandir( $directory );
	array_shift( $contents ); // skip .
	array_shift( $contents ); // skip ..

	return $contents;
}

/**
 * Handle the read file resource
 *
 * @param $file
 */
function processInstance( $filepath )
{
	global $instanceId, $instance_buffer;

	// Process 500 at a time to speed up queries
	if( count( $instance_buffer ) == 50 )
	{ executeBuffer(); }


	$instance = json_decode( file_get_contents( $filepath ) );

	if ( ! $instance || empty( $instance ))
	{
		var_dump( $filepath );
		exit( "failed to decode json!\n" );
	}

	importInstance( $instance );
	importInstanceProperties( $instance->properties );
	$instanceId++;
}

/**
 * Process the instance and import to database
 *
 * @param $instance
 */
function importInstance( $instance )
{
	global $db, $levelIndex, $instance_buffer, $property_buffer;

	$position   = explode( ' ', $instance->position );
	$position_x = $position[0];
	$position_y = $position[1];
	$position_z = $position[2];

	// include quotes where appropriate for sql statement
	$values = array(
		"'$instance->class'",
		"'$instance->hash'",
		"'$levelIndex'",
		"'$instance->header'",
		"'$instance->meta1'",
		"'$instance->meta2'",
		"'$instance->meta3'",
		"$position_x",
		"$position_y",
		"$position_z",
		"'$instance->data1'",
		"'$instance->data2'",
		"'$instance->flag'",
		"'$instance->render'",
		"'$instance->propertyCount'",
	);
	// push to buffer
	$valuestring = implode( ', ', $values );
	$instance_buffer[] = "( $valuestring )";
}

function insertInstances()
{
	global $db, $instance_buffer;
	print "Instering isntances into db\n";

	$values = implode( ', ', $instance_buffer );
	$sql = "INSERT INTO mesh_instances (class, hash, level_id, header, "
		. "meta1, meta2, meta3, position_x, position_y, position_z, "
		. "data1, data2, flag, render, property_count) "
		. "VALUES $values;";

	$success = $db->exec( $sql );

	if ( ! $success )
	{
		var_dump( $sql );
		var_dump( $success );
		exit( "failed to add mesh_instance!\n" );
	}
}

/**
 * Add all of the instance properties
 *
 * @param $properties
 */
function importInstanceProperties( $properties )
{
	$properties = json_decode( json_encode( $properties ), true );
	//convert to an array for easier traversal

	if( is_array( $properties ) )
	{
		foreach( $properties as $key => $data )
		{
			importInstanceProperty( $key, $data );
		}
	}

}

/**
 * Add a mesh_instance_properties record
 *
 * @param $key
 * @param $data
 * @param $instanceId
 */
function importInstanceProperty( $key, $data )
{
	global $levelIndex, $property_buffer, $instanceId;

	$data = json_decode( json_encode( $data ) ); // convert to an object for syntactic sugar
	// include quotes. these are for sql insert
	$values = array(
		"'$instanceId'",
		"'$levelIndex'",
		"'$key'",
		"'$data->flag'",
		"'$data->data'",
		"'$data->texture'",
	);
	// push to buffer
	$valuestring = implode( ', ', $values );
	$property_buffer[] = "( $valuestring )";
}

function insertProperties()
{
	global $db, $property_buffer;
	print "Instering instance properties into db\n";

	$values = implode( ', ', $property_buffer );
	$sql  = "INSERT INTO mesh_instance_properties (instance_id, level_id, "
	  . "prop_name, prop_flag, prop_data, prop_texture) "
	  . "VALUES $values;";

	$success = $db->exec( $sql );

	if ( ! $success )
	{
		var_dump( $sql );
		var_dump( $success );
		exit( "ERROR!\n" );
	}
}

function executeBuffer()
{
	global $instance_buffer, $property_buffer;

	// Do inserts
	insertInstances();
	insertProperties();

	// flush buffer
	$instance_buffer = array();
	$property_buffer = array();
}

openDatabase();
loopThroughLevels();

if( ! empty( $instance_buffer ) )
{ executeBuffer(); }
