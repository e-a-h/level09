<?php
require_once 'Helper.php';

// this could take a while...
ini_set("memory_limit", "-1");
set_time_limit(0);

Helper::helpMe( array( Helper::HelpMultiLevel ) );

/*      state variables     */
// stores our current decoration mesh until we're ready to write to file
$instance = array();
// line counter, to know what data we're looking at, and when to write to file
$lineCount = 0;
// count how many instances we've processed, used as index when rebuilding
$instanceCount = 0;

/**
 * Loop through each level's DecorationMeshInstances to process, and manage state between files
 */
function loopThroughLevels()
{
	global $lineCount, $instanceCount;
	$levels = Helper::filterLevels();

	foreach( $levels as $level )
	{
		$lineCount = $instanceCount = 0;
		handleFile( "Level_$level" );
	}
}

/**
 * Open the level's DecorationMeshInstances file and process it
 */
function handleFile( $directory )
{
	$readfile = fopen( "$directory/DecorationMeshInstances.lua.bin", "r" );

	if( $readfile )
	{ processFile( $readfile, $directory ); }

	fclose( $readfile );
}

/**
 * Read the file and extract objects
 *
 * @param $readfile
 */
function processFile( $readfile, $directory )
{
	//throw away the first line
	$line = fread( $readfile, 16 );

	while( ! feof( $readfile ) )
	{
		$line = fread( $readfile, 16 );
		extractObject( $line, $directory );
	}
}

/**
 * Save the line to the global object. Also saves the hash and class for later use.
 *
 * @param $line
 * @return int
 */
function extractObject( $line, $directory )
{
	global $lineCount;

	extractObjectData( $line );

	//we're at the end of the block
	if( ( $lineCount + 1 ) % 132 == 0 )
	{
		formatProperties();
		writeFile( $directory );
	}


	$lineCount++;
}

function extractObjectData( $line )
{
	global $instance, $lineCount;

	// these lines should be decoded to hex
	if( ( $lineCount == 0 ) || ( $lineCount >= 7 ) )
	{ $line = bin2hex( $line ); }

	switch( $lineCount )
	{
		case 0:
			$instance['header'] = substr( $line, 0, 8 );
			break;
		case 1:
			$instance[ 'meta1' ] = Helper::unpackStringOfFloats( substr( $line, 0, 12 ) );
			break;
		case 2:
		  $instance[ 'meta2' ] = Helper::unpackStringOfFloats( substr( $line, 0, 12 ) );
			break;
		case 3:
			$instance[ 'meta3' ] = Helper::unpackStringOfFloats( substr( $line, 0, 12 ) ); 
			break;
		case 4:
			$instance[ 'position' ] = Helper::unpackStringOfFloats( substr( $line, 0, 12 ) ); 
			break;
		case 5:
			$instance[ 'data1' ] = Helper::unpackStringOfFloats( $line );
			break;
		case 6:
			$instance['data2'] = Helper::unpackStringOfFloats( substr( $line, 0, 12 ) );
			$instance['flag'] = bin2hex( substr( $line, 14, 4 ) );
			break;
		case 7:
			// initialize
			$instance['hash'] = extractValue( $line );
			break;
		case 8:
			$instance['hash'] .= extractValue( $line );
			break;
		case 9:
			break;
		case 10:
			break;
		case 11:
			// initialize
			$instance['class'] = extractValue( $line );
			break;
		case 12:
		case 13:
			$instance['class'] .= extractValue( $line );
			break;
		case 14:
			break;
		case 15:
			// initialize
			$instance['render'] = extractValue( $line );
			break;
		case 16:
			$instance['render'] .= extractValue( $line );
			break;
		case 18:
			// initialize
			$instance['properties'] = '';
			break;
		case 131:
			$instance['propertyCount'] = substr( $line, 4, 4 );
			break;
		default:
			break;
	}

	//gather property list to parse later
	if( $lineCount > 18 && $lineCount < 131 )
	{ $instance['properties'] .= $line . '|'; }
}

/**
 * Extract value from a line, preferring ascii over float interpretation
 * You can pass 'true' as the second parameter to force conversion to float
 * If we do translate floats, include the final byte group if not empty
 * @param $line
 * @param bool $avoidAscii
 * @return string
 */
function extractValue( $line, $avoidAscii = false )
{
	if( ( ! $avoidAscii ) &&
	    mb_check_encoding( Helper::hex2str( $line ), 'ASCII' ) )
	{ return Helper::hex2str( $line ); }

	$line = hex2bin( $line );
	$line = substr( $line, 12, 4 ) ? $line : substr( $line, 0, 12 );

	return Helper::unpackStringOfFloats( $line );
}

function formatProperties()
{
	global $instance;

	$propArray = explode( '|', rtrim( $instance[ 'properties' ], '|' ) );
	$instance[ 'properties' ] = array();

	for( $i = 0; $i < 16; $i++ )
	{ formatProperty($i, $propArray); }
}

/**
 * @param $i
 * @param $propArray
 * @param $asdf
 * @return mixed
 */
function formatProperty( $i, $propArray )
{
	global $instance;

	$base = $i * 7;
	$propertyName = extractValue( $propArray[ $base + 5 ] );

	if($propertyName)
	{
		$propertyData = extractValue( substr( $propArray[ $base ], 0, 24 ), true );
		$propertyTexture = extractValue( $propArray[ $base + 1 ] . $propArray[ $base + 2 ] );
		$propertyFlag = substr( $propArray[ $base + 6 ], 4, 4);

		$instance[ 'properties' ][ $propertyName ] = array(
		  'flag' => $propertyFlag,
		  'data'  => $propertyData,
		  'texture' => $propertyTexture
		);
	}
}

/**
 * Write the object to a file
 */
function writeFile( $directory )
{
	global $instance;

	$path = prepareOutput( $directory );
	file_put_contents( $path, json_encode( $instance ) );
	resetState();
}

/**
 * Prepare the output file path, and return it
 * We also
 * @return string
 */
function prepareOutput( $directory )
{
  global $instance, $instanceCount;

  $classSubdir = "$directory/DecorationMeshInstances/" . $instance['class'];
  if( $basepath = Helper::validateDirectory( $classSubdir ) )
  {
	  $path = "$basepath/$instanceCount-" . $instance['hash'] . '.json';
	  echo "Extracting to $classSubdir/$instanceCount-" . $instance['hash'] . ".json\r\n";
	  return $path;
	}
  echo "Failed to construct path from $directory";
	exit();
}

function resetState()
{
	global $instance, $lineCount, $instanceCount;

	$instance = array();
	$lineCount = -1;
	$instanceCount++;
}

loopThroughLevels();
