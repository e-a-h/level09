<?php
require_once 'Helper.php';
require_once 'HullInstance.php';

// config variables
// what levels will we extract?
$levels = array(
  'Barrens', 'Bryan', 'Canyon', 'Cave',
  'Chris', 'Credits', 'Desert', 'Graveyard',
  'Matt', 'Mountain', 'Ruins', 'Summit',
);

$options = getopt( "l:" );
if( ! empty( $options ) && ! empty( $options['l'] ) )
{
	$levels = explode( ',', $options['l'] );
}

// state variables
$directory = '';
$uid      = '';
$class     = '';
$object    = '';
$counter   = 0;
$set       = 0;

/**
 * Loop through each level's DecorationMeshInstances to process, and manage state between files
 */
function loopThroughLevels()
{
	global $levels, $directory, $counter, $set;

	foreach ($levels as $level)
	{
		$counter   = $set   = 0;
		$directory = "Level_$level";

		handleFile();
	}
}

/**
 * Open the level's HullInstances file and process it
 */
function handleFile()
{
	global $directory;
	$handle = fopen("$directory/HullInstances.lua.bin", "r");

	if ( $handle )
	{ processFile( $handle ); }

	fclose( $handle );
}

/**
 * Read the file and extract objects
 *
 * @param $handle
 */
function processFile( $handle )
{
	global $directory;
	// Filesize is unsigned long starting at offset 0x4
	fseek( $handle, 4 );
	$fsbin = fread( $handle, 4 );
	$filesize = current( unpack( 'N', $fsbin ) );

	fseek( $handle, 16 );

	// Header start delimiter looks something like
	// 011431A0 00000000 00000000 00000000
	// where
	// 011431   is instance count?
	//       A0 is header length
	// The rest is zero-padding
	$delimiter = fread( $handle, 16 );

	fseek( $handle, 19 );
	$headersize = fread( $handle, 1 );

	// seek to start of first instance
	fseek( $handle, 16 );

	HullInstance::setDirectory( $directory );
	$Instances = array();
	$NextInstanceOffset = ftell( $handle );
	// The last "NextInstanceOffset" is 0x00000000 and should break this loop
	while( $NextInstanceOffset )
	{
		$NextInstanceOffset = unpackInstance( $handle, $NextInstanceOffset, $Instances );
	}
	Helper::plog("$directory Instance count: ".count($Instances));

	// TODO: convert the $Instances object to db
}

/// \brief unpack a hull instance
function unpackInstance( $handle, $StartOffset, &$Instances )
{
	global $directory;
	// Go to instance start, plus one row
	fseek( $handle, $StartOffset+16 );

	// get some vectors. what are they? nobody knows.
	$a = Helper::unpackFloatVector( fread( $handle, 12 ) );
	fseek( $handle, 4, SEEK_CUR );
	$b = Helper::unpackFloatVector( fread( $handle, 12 ) );
	fseek( $handle, 4, SEEK_CUR );
	$c = Helper::unpackFloatVector( fread( $handle, 12 ) );
	fseek( $handle, 4, SEEK_CUR );
	$d = Helper::unpackFloatVector( fread( $handle, 12 ) );
	fseek( $handle, 4, SEEK_CUR );

	// Array of offsets for face,index,edge,vert
	$Offsets = Helper::unpackFourLong( $handle );

	// Array of counts for face,index,edge,vert
	$Counts = Helper::unpackFourLong( $handle );

	// Unknown values
	$MysteryLongA = Helper::extractLong( $handle );
	$MysteryLongB = Helper::extractLong( $handle );
	$MysteryFloat = Helper::extractFloat( $handle );

	// skip 4 bytes of zero-padding
	fseek( $handle, 4, SEEK_CUR );

	// Offset for the beginning of the next instance
	$NextInstanceOffset = Helper::extractLong( $handle );
	$uid = fread( $handle, 32 );

	// array of char
	$Faces = array();
	$FaceOffset = $Offsets[0];
	$FaceCount = $Counts[0];
	fseek( $handle, $FaceOffset );
	$FacesBin = fread( $handle, $FaceCount * 3 );
	$FacesSplit = str_split( $FacesBin, 3 );
	foreach( $FacesSplit as $value )
	{ $Faces[] = Helper::unpackCharArray( $value ); }

	// array of char
	$Index = array();
	$IndexOffset = $Offsets[1];
	$IndexCount = $Counts[1];
	fseek( $handle, $IndexOffset );
	$IndexBin = fread( $handle, $IndexCount );
	$IndexSplit = str_split( $IndexBin );
	foreach( $IndexSplit as $value )
	{ $Index[] = Helper::unpackCharArray( $value ); }

	// array of char
	$Edges = array();
	$EdgeOffset = $Offsets[2];
	$EdgeCount = $Counts[2];
	fseek( $handle, $EdgeOffset );
	$EdgesBin = fread( $handle, $EdgeCount * 2 );
	$EdgesSplit = str_split( $EdgesBin, 2 );
	foreach( $EdgesSplit as $value )
	{ $Edges[] = Helper::unpackCharArray( $value ); }

	// array of float vector
	$Verts = array();
	$VertOffset = $Offsets[3];
	$VertCount = $Counts[3];
	fseek( $handle, $VertOffset );
	$VertsBin = fread( $handle, $VertCount * 16 );
	$VertsSplit = str_split( $VertsBin, 16 );
	foreach( $VertsSplit as $value )
	{ $Verts[] = Helper::unpackFloatVector( substr( $value, 0, 12 ) ); }

	// All done. Go to the next instance.
	if( $NextInstanceOffset )
	{ fseek( $handle, $NextInstanceOffset ); }

	$Instance = array
	(
		"a" => $a,
		"b" => $b,
		"c" => $c,
		"d" => $d,
		"Offsets" => $Offsets,
		"Counts" => $Counts,
		"MysteryLongA" => $MysteryLongA,
		"MysteryLongB" => $MysteryLongB,
		"MysteryFloat" => $MysteryFloat,
		"uid" => $uid,
		"vertices" => $Verts,
		"faces" => $Faces,
		"edges" => $Edges,
		"index" => $Index,
	);

	$Hull = new HullInstance( $Instance );
	$Hull->exportObj();

	$Instances[] = $Hull;
	return $NextInstanceOffset;
}

loopThroughLevels();
