<?php
require_once 'Helper.php';

// this could take a while...
ini_set("memory_limit", "-1");
set_time_limit(0);
// used for backup filenames
date_default_timezone_set("America/Chicago");

Helper::helpMe( array( Helper::HelpMultiLevel ) );
$levels = Helper::filterLevels();

/**
 * Loop through each level's DecorationMeshInstances to process, and manage state between files
 */
function loopThroughLevels()
{
	global $levels;

	foreach($levels as $level) {
		backupLevel($level);
		rebuildLevel($level);
	}
}

/**
 * Backup the output file before writing. Don't lose good work.
 *
 * @param $level
 */
function backupLevel($level)
{
	$path = getOutputPath($level);
	if(realpath($path)) {
		$backupDirectory = "Level_$level/Backups";
		ensureDirectoryExists($backupDirectory);
		rename($path, getBackupPath($backupDirectory));
	}
}



/* Path helpers */

/**
 * Get the path to the output file
 *
 * @param $level
 * @return string
 */
function getOutputPath($level)
{
	return "Level_$level/DecorationMeshInstances.lua.bin";
}

/**
 * Get the path to the backup directory
 *
 * @param $level
 * @return string
 */
function getBackupPath($directory)
{
	return "$directory/DecorationMeshInstances " . date('Y-m-d h:i:s') . ".lua.bin" ;
}

/**
 * Get the path to the extracted archive
 *
 * @param $level
 * @return string
 */
function getArchivePath($level)
{
	return "Level_$level/DecorationMeshInstances/";
}

/**
 * Create the directory if it doesn't exist
 *
 * @param $directory
 */
function ensureDirectoryExists($directory)
{
	if(!realpath($directory))
		mkdir(realpath("./") . '/' . $directory, 0777, TRUE);
}

/**
 * This skips all hidden files & directories
 *
 * @param $directory
 * @return array
 */
function getDirectoryContents($directory)
{
	$contents = scandir($directory);

	// ignore hidden files & directories
	while(strpos($contents[0], '.') === 0)
		array_shift($contents);

	return $contents;
}



/* Generate new output file */

/**
 * Rebuild the level's DecorationMeshInstances.lua.bin file from our JSON files
 *
 * @param $level
 * @param $key
 */
function rebuildLevel($level)
{
	echo "Rebuilding ".getOutputPath($level). PHP_EOL;

	$instances = orderInstances($level);
	$writefile = fopen(getOutputPath($level), "w");
	fwrite($writefile, getHeader(sizeof($instances)));

	foreach($instances as $instance)
		processInstance($instance, $writefile);

	fclose($writefile);
}

/**
 * Organize our instances to restore the original order
 *
 * @param $level
 * @return array
 */
function orderInstances($level)
{
	$instances = array();

	foreach(getDirectoryContents(getArchivePath($level)) as $class) {
		foreach(getDirectoryContents(getArchivePath($level) . $class) as $filename) {
			if(isJsonFile($filename)) {
				$index = substr($filename, 0, strpos($filename, '-'));
				$instances[$index] = getArchivePath($level) . "$class/$filename";
			}
		}
	}

	ksort($instances);

	return $instances;
}

/**
 * Generate the file's header line, which includes a count of instances in the level
 *
 * @param $level
 * @return string
 */
function getHeader($instanceCount)
{
	$instanceCount = str_pad(dechex($instanceCount), 4, '0', STR_PAD_LEFT);
	$header = hex2bin("000000010000");
	$header .= hex2bin($instanceCount);
	$header .= hex2bin(pad(4));

	return $header;
}

/**
 * Check if a file is one of our JSON-encoded instances
 *
 * @param $filename
 * @return string
 */
function isJsonFile($filename)
{
	return stristr($filename, '.json');
}



/* Process json files and generate binary */

/**
 * Handle the read file resource
 *
 * @param $readfile
 */
function processInstance($readfile, $writefile)
{
	$instance = json_decode(file_get_contents($readfile));

	if(!$instance || empty($instance)) {
		var_dump($readfile);
		die('failed to decode json!');
	}

	$instanceBin = rebuildInstance($instance);

	fwrite($writefile, $instanceBin);
}

/**
 * Compile the instance into binary
 *
 * @param $instance
 * @return string
 */
function rebuildInstance($instance)
{
	$instanceBin = hex2bin($instance->header . pad(6));
	$instanceBin .= floatTuple2Bin($instance->meta1);
	$instanceBin .= floatTuple2Bin($instance->meta2);
	$instanceBin .= floatTuple2Bin($instance->meta3);
	$instanceBin .= floatTuple2Bin($instance->position, false);
	$instanceBin .= hex2bin("3f800000");
	$instanceBin .= floatTuple2Bin($instance->data1);
	$instanceBin .= floatTuple2Bin($instance->data2, false);
	$instanceBin .= hex2bin(pad(1) . $instance->flag);
	$instanceBin .= str2bin($instance->hash);
	$instanceBin .= str2bin($instance->class);
	$instanceBin .= str2bin($instance->render);
	$instanceBin .= rebuildProperties($instance->properties);

	return $instanceBin;
}

/**
 * Rebuild binary for the instance properties. Return binary string and count
 *
 * @param $properties
 * @return array
 */
function rebuildProperties($properties)
{
	$propertiesBin = '';
	$count = 0;

	foreach((array) $properties as $property => $data) {
		$propertiesBin .= rebuildProperty($property, $data);
		$count++;
	}

	// pad the remaining space
	$padding = 16 - $count;
	while($padding > 0) {
		$propertiesBin .= hex2bin(pad(56));
		$padding--;
	}

	// add the property count based on what we've processed,
	// rather than using the value listed in the instance
	$hexCount = str_pad(dechex($count), 4, '0', STR_PAD_LEFT);
	$propertiesBin .= hex2bin(pad(1) . $hexCount . pad(6));

	return $propertiesBin;
}

/**
 * Rebuild binary representation of an instance property
 *
 * @param $property
 * @return string
 */
function rebuildProperty($property, $data)
{
	$propertyBin = floatTuple2Bin($data->data);
	$propertyBin .= str2bin($data->texture);
	$propertyBin .= str2bin($property, 1);
	$propertyBin .= hex2bin(pad(1) . $data->flag . pad(6));

	return $propertyBin;
}



/* Formatting helpers */

/**
 * Return a 0-filled string. A chunk is 4 hex characters
 *
 * @param $chunks
 */
function pad($chunks)
{
	return str_repeat("0",$chunks*4);
}

/**
 * Take a string and convert into a float tuple
 * Pass 'false' into the second parameter to avoid including a fourth value
 *
 * @param $line
 * @param bool $includeLast
 * @return string
 */
function floatTuple2Bin($line, $includeLast = true)
{
	$tuple = explode(' ', $line);
	$output = float2bin(array_shift($tuple));
	$output .= float2bin(array_shift($tuple));
	$output .= float2bin(array_shift($tuple));

	if($includeLast)
		$output .= empty($tuple)? hex2bin(pad(2)) : float2bin(array_shift($tuple));

	return $output;
}

/**
 * Pack a float into binary
 * @param $float
 * @return string
 */
function float2bin($float)
{
	$bin = pack('f', $float);
	return correctEndianness($bin);
}

/**
 * Correct binary direction
 *
 * @param $binary
 * @return string
 */
function correctEndianness($binary) {
	if(isLittleEndian())
		return strrev( $binary );

	return $binary;
}

/**
 * Test for little endianness
 * @return bool
 */
function isLittleEndian() {
	$testint = 0x00FF;
	$p = pack('S', $testint);

	return $testint === current(unpack('v', $p));
}

/**
 * Translate ascii into hex
 *
 * @param $string
 * @param $length
 * @return string
 */
function str2bin($string, $lines = 4)
{
	$hex = $output = '';

	foreach(str_split($string) as $character)
		$hex .= dechex(ord($character));

	$hex = str_pad($hex, $lines*32, '0');

	foreach(array_chunk(str_split($hex), 32) as $line)
		$output .= hex2bin(implode($line));

	return $output;
}



loopThroughLevels();