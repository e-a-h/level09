<?php
require_once 'Helper.php';

// Flag to export maya locator script:
// php PlotPositions.php -m
$options = getopt( "m" );
$exportMEL = isset( $options['m'] );
Helper::helpMe( array( Helper::HelpMel, Helper::HelpSingleLevel ) );

// get level from -l flag
$level = Helper::filterLevels(true);

$globalclass;

// unique descriptor for your output file name
$descriptor = 'meshinstance';

$minx = 9999;
$miny = 9999;
$minz = 9999;
$maxx = -9999;
$maxy = -9999;
$maxz = -9999;

// what class names do you want?
// uses preg_match, so you can get partial matches
$search_keys = array( '.*' );

// $search_keys = array('Rock', 'Flag', 'Tapestry');

// define a color index for the class plot.
// uses preg_match, so you can get partial matches
$colorKeys = array(
  'FlagLg'                  => 0,
  'FlagMesh'                => 1,
  'P_MeshTapestry'          => 2,
  'P_Tapestry2'             => 3,
  'P_BarrensRiverRock'      => 4,
  'P_BarrensTunnelRockWall' => 5,
  'P_M6Rock'                => 6,
  'P_MountainRockB'         => 7,
  'P_RockBackWall'          => 8,
  'RockBed'                 => 9,
);

/* state variables */
// color of plot point (int)
$color = 0;

// line counter
$counter = 0;

/**
 * Scan the DecorationMeshInstances directory for the current level,
 * and prepare the write file
 */
function scanInstances()
{
	global $level, $search_keys, $descriptor, $globalclass, $exportMEL;
	global $minx, $miny, $minz, $maxx, $maxy, $maxz;

	$classes = getDirectoryContents("Level_$level/DecorationMeshInstances");
	$Ext = $exportMEL ? 'mel' : 'txt';
	$writefile = fopen("Level_$level/$descriptor-positions.$Ext", "w");

	foreach( $search_keys as $key )
	{
		foreach( $classes as $class )
		{
			$globalclass = $class;
			processClass( $key, $class, $writefile );
		}
	}

	// add north/south/east/west labels using point cloud bounds
	$avx = ( $minx + $maxx ) / 2;
	$avy = ( $miny + $maxy ) / 2;
	$avz = ( $minz + $maxz ) / 2;

	$globalclass = "north";
	writePosition( $writefile, $maxx, $avy, 0, "north", 0 );
	$globalclass = "south";
	writePosition( $writefile, $minx, $avy, 0, "south", 0 );
	$globalclass = "east";
	writePosition( $writefile, $avx, $miny, 0, "east", 0 );
	$globalclass = "west";
	writePosition( $writefile, $avx, $maxy, 0, "west", 0 );

	fclose( $writefile );
	/*
	After this, you can plot in gnuplot using:
	gnuplot> set xlabel "x axis"; set ylabel "y axis"; set zlabel "z axis"; set view equal xyz
	gnuplot> splot 'Level_Barrens/rock-flag-positions.txt' u 1:2:3:4:5 w labels tc palette offset 0,-1 point palette
 */
}

// output a line to the output file
function writePosition( $handle, $x, $y, $z, $label="", $index=0 )
{
	global $exportMEL, $globalclass;
	if( $exportMEL )
	{
		fwrite( $handle, "spaceLocator -p $x $y $z" );
		if( ! empty( $label ) )
		{ fwrite( $handle, " -n \"$label\";" ); }
		fwrite( $handle, "\r\n" );
		print "Added locator $globalclass to script\n";
	}
	else
	{
		fwrite( $handle, "$x $y $z $label $index\r\n" );
		print "Added $globalclass to output\n";
	}
}


/**
 * This skips the first 2 lines of output (current & parent directory)
 * @param $directory
 * @return array
 */
function getDirectoryContents($directory)
{
	$contents = scandir( $directory );
	array_shift( $contents ); // skip .
	array_shift( $contents ); // skip ..

	if( empty( $contents ) )
	{
		exit( "No mesh instances found in $directory.\nTry ExtractLevels script first." );
	}
	return $contents;
}

/**
 * Check if the key is in the class, and process if so
 *
 * @param $key
 * @param $class
 * @param $writefile
 */
function processClass($key, $class, $writefile)
{
	global $level, $color;

	if( preg_match( "/$key/", $class ) )
	{
		$directory = "Level_$level/DecorationMeshInstances/$class";
		$color     = ++$color;
		// setColor($class);
		processFiles( $directory, $writefile );
	}
}

/**
 * Set the color for the class. This could be changed to increment color.
 *
 * @param $class
 */
function setColor($class)
{
	global $colorKeys, $color;
	$color = 0;

	foreach ($colorKeys as $key => $value)
	{
		if (preg_match("/$key/", $class))
		{
			$color = $value;
		}
	}

}

/**
 * Get files from the directory, and output their object positions to the writefile.
 *
 * @param $directory
 * @param $writefile
 */
function processFiles($directory, $writefile)
{
	$files = getDirectoryContents($directory);
	foreach( $files as $file )
	{
		if( ! stristr( $file, '.' ) )
		{ handleFile( $directory, $writefile, $file ); }
	}
}

/**
 * Open the file for reading and reset line counter
 *
 * @param $directory
 * @param $writefile
 * @param $file
 */
function handleFile( $directory, $writefile, $file )
{
	global $counter;

	$counter  = 0;
	$readfile = fopen("$directory/$file", "rb");

	// print "$directory/$file\n";
	if ($readfile && $writefile)
	{ processFile($readfile, $writefile); }

	fclose($readfile);
}

/**
 * Read each line of the file, translate to hex, and extract position
 *
 * @param $readfile
 * @param $writefile
 */
function processFile($readfile, $writefile)
{
	global $counter;

	while ( ! feof( $readfile ) )
	{
		$binline = fread($readfile, 16);
		extractPosition($writefile, $binline);
		$counter++;
	}

}

/**
 * Extract the position from the hex provided, and write to output file
 *
 * @param $writefile
 * @param $line
 * @return int
 */
function extractPosition( $writefile, $line )
{
	global $counter, $color, $globalclass;
	if ($counter == 4)
	{
		// The order coordinates appear in the hex is y,z,x
		// Format to floats
		$y = Helper::unpackFloat( substr( $line, 0, 4 ) );
		$z = Helper::unpackFloat( substr( $line, 4, 4 ) );
		$x = Helper::unpackFloat( substr( $line, 8, 4 ) );

		setBounds( $x, $y, $z );

		// output the coords and color index;
		$Label = preg_replace( '/P_/', '', $globalclass );
		$Label = preg_replace( '/C_/', '', $Label );
		$Label = preg_replace( '/_/', '-', $Label );
		writePosition( $writefile, $x, $y, $z, $Label, $color );
	}
}

function setBounds( $x, $y, $z )
{
	global $minx, $miny, $minz, $maxx, $maxy, $maxz;

	if( $x > $maxx )
	{ $maxx = $x; }

	if( $y > $maxy )
	{ $maxy = $y; }

	if( $z > $maxz )
	{ $maxz = $z; }

	if( $x < $minx )
	{ $minx = $x; }

	if( $y < $miny )
	{ $miny = $y; }

	if( $z < $minz )
	{ $minz = $z; }
}

scanInstances();
