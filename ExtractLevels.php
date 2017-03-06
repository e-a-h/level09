<?php
require_once 'Helper.php';

// state variables
$directory = '';
$hash = '';
$class = '';
$instance = '';
$counter = 0;
Helper::helpMe( array( Helper::HelpMultiLevel ) );

/**
 * Loop through each level's DecorationMeshInstances to process, and manage state between files
 */
function loopThroughLevels()
{
	global $directory, $counter;

	$levels = Helper::filterLevels();
	foreach( $levels as $level )
	{
		$counter = 0;
		$directory = "Level_$level";
		handleFile();
	}
}

/**
 * Open the level's DecorationMeshInstances file and process it
 */
function handleFile()
{
	global $directory;
	$readfile = fopen("$directory/DecorationMeshInstances.lua.bin", "r");

	if( $readfile )
	{ processFile( $readfile ); }

	fclose( $readfile );
}

/**
 * Read the file and extract instances
 *
 * @param $readfile
 */
function processFile( $readfile )
{
	//throw away the first line
	$line = fread( $readfile, 16 );

	while( ! feof( $readfile ) )
	{
		$line = bin2hex( fread( $readfile, 16 ) );
		extractInstance( $line );
	}
}

/**
 * Save the line to the global instance. Also saves the hash and class for later use.
 *
 * @param $line
 * @return int
 */
function extractInstance( $line )
{
	global $counter, $instance;

	extractHash( $line );
	extractClass( $line );
	$instance .= $line;

	//we're at the end of the block
	if( ( ( $counter + 1 ) % 132 ) == 0 )
	{ writeFile(); }

	$counter++;
}

/**
 * Extract the hash from the DecorationMeshInstance
 *
 * @param $line
 */
function extractHash( $line )
{
	global $counter, $hash;

	if( ( $counter == 7 ) || ( $counter == 8 ) )
	{ $hash .= $line; }
}

/**
 * Extract the class name from the DecorationMeshInstance
 *
 * @param $line
 */
function extractClass( $line )
{
	global $counter, $class;

	if( ( $counter == 11 ) || ( $counter == 12 ) )
	{ $class .= $line; }
}

/**
 * Write the instance to a file
 */
function writeFile()
{
	global $directory, $hash, $class, $instance;

	$instance = hex2bin( $instance );
	$class = Helper::hex2str( $class );
	$hash = Helper::hex2str( $hash );
	$path = prepareOutput();

	echo "Extracting $directory/$class/$hash\n";
	file_put_contents( $path, $instance );
	resetState();
}

/**
 * Prepare the output file path, and return it
 * We also
 * @return string
 */
function prepareOutput()
{
	global $hash, $class, $directory;

	$classSubdir = "$directory/DecorationMeshInstances/$class";
	if( $basepath = Helper::validateDirectory( $classSubdir ) )
	{ return "$basepath/$hash"; }

	echo "Failed to construct path from $directory";
	exit();
}

function resetState()
{
	global $hash, $class, $instance, $counter;

	$hash = $class = $instance = '';
	$counter = -1;
}

loopThroughLevels();
