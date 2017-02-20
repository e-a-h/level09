<?php
// this could take a while...
ini_set("memory_limit", "-1");
set_time_limit(0);

/*      config variables    */
// what levels will we extract?
$levels = array(
  'Barrens', 'Bryan', 'Canyon', 'Cave',
  'Chris', 'Credits', 'Desert', 'Graveyard',
  'Matt', 'Mountain', 'Ruins', 'Summit'
);

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
function loopThroughLevels() {
	global $levels, $lineCount, $instanceCount;

	foreach($levels as $level) {
		$lineCount = $instanceCount = 0;
		handleFile("Level_$level");
	}
}

/**
 * Open the level's DecorationMeshInstances file and process it
 */
function handleFile($directory) {
	$readfile = fopen("$directory/DecorationMeshInstances.lua.bin", "r");

	if($readfile)
		processFile($readfile, $directory);

	fclose($readfile);
}

/**
 * Read the file and extract objects
 *
 * @param $readfile
 */
function processFile($readfile, $directory) {
	//throw away the first line
	$line = fread($readfile, 16);

	while(!feof($readfile)) {
		$line = fread($readfile, 16);
		extractObject($line, $directory);
	}
}

/**
 * Save the line to the global object. Also saves the hash and class for later use.
 *
 * @param $line
 * @return int
 */
function extractObject($line, $directory) {
	global $lineCount;

	extractObjectData($line);

	//we're at the end of the block
	if(($lineCount+1)%132 == 0) {
		formatProperties();
		writeFile($directory);
	}


	$lineCount++;
}

function extractObjectData($line) {
	global $instance, $lineCount;

	// these lines should be read as binary
	if($lineCount == 1)
		$instance['meta1'] = translateFloatTuple($line);
	if($lineCount == 2)
		$instance['meta2'] = translateFloatTuple($line);
	if($lineCount == 3)
		$instance['meta3'] = translateFloatTuple($line);
	if($lineCount == 4)
		$instance['position'] = translateFloatTuple($line);
	if($lineCount == 5)
		$instance['data1'] = translateFloatTuple($line, true);
	if($lineCount == 6) {
		$instance['data2'] = translateFloatTuple($line);
		$instance['flag'] = bin2hex(substr($line, 14, 4));
	}

	// these lines should be read as hex or ascii
	$line = bin2hex($line);

	if($lineCount == 0)
		$instance['header'] = substr($line, 0, 8);
	if($lineCount == 7 || $lineCount == 8)
		$instance['hash'] .= extractValue($line);
	if($lineCount == 11 || $lineCount == 12 || $lineCount == 13)
		$instance['class'] .= extractValue($line);
	if($lineCount == 15 || $lineCount == 16)
		$instance['render'] .= extractValue($line);

	//gather property list to parse later
	if($lineCount > 18 && $lineCount < 131)
		$instance['properties'] .= $line . '|';
	if($lineCount == 131)
		$instance['propertyCount'] .= substr($line, 4, 4);
}

/**
 * Extract value from a line, preferring ascii over float interpretation
 * You can pass 'true' as the second parameter to force conversion to float
 * If we do translate floats, include the final byte group if not empty
 * @param $line
 * @param bool $avoidAscii
 * @return string
 */
function extractValue($line, $avoidAscii = false) {
	if(!$avoidAscii && mb_check_encoding(hex2str($line), 'ASCII'))
		return hex2str($line);

	$line = hex2bin($line);
	$includeLast = substr($line, 12, 4) ? true : false;

	return translateFloatTuple($line, $includeLast);
}

function translateFloatTuple($line, $includeLast = false) {
	$x = bin2float(substr($line, 0, 4));
	$y = bin2float(substr($line, 4, 4));
	$z = bin2float(substr($line, 8, 4));

	if($includeLast)
		$a = bin2float(substr($line, 12, 4));

	// output the float values
	return "$x $y $z" . ($includeLast ? " $a" : "");
}

function bin2float($binary) {
	return current( unpack( 'f', correctEndianness( $binary ) ) );
}

function correctEndianness($binary) {
	if(isLittleEndian())
		return strrev( $binary );

	return $binary;
}

function isLittleEndian() {
	$testint = 0x00FF;
	$p = pack('S', $testint);

	return $testint === current(unpack('v', $p));
}

function formatProperties() {
	global $instance;

	$propArray = explode('|', rtrim($instance['properties'], '|'));
	$instance['properties'] = array();

	for($i=0;$i<16;$i++)
		formatProperty($i, $propArray);
}

/**
 * @param $i
 * @param $propArray
 * @param $asdf
 * @return mixed
 */
function formatProperty($i, $propArray) {
	global $instance;

	$base = $i * 7;
	$propertyName = extractValue($propArray[$base+5]);

	if($propertyName) {
		$propertyData = extractValue(substr($propArray[$base], 0, 24), true);
		$propertyTexture = extractValue($propArray[$base + 1] . $propArray[$base + 2]);
		$propertyFlag = substr($propArray[$base + 6], 4, 4);

		$instance['properties'][$propertyName] = array(
		  'flag' => $propertyFlag,
		  'data'  => $propertyData,
		  'texture' => $propertyTexture
		);
	}
}

/**
 * Write the object to a file
 */
function writeFile($directory) {
	global $instance;

	$path = prepareOutput($directory);
	file_put_contents($path, json_encode($instance));
	resetState();
}

/**
 * Prepare the output file path, and return it
 * We also
 * @return string
 */
function prepareOutput($directory) {
	global $instance, $instanceCount;

	$classSubdir = "$directory/DecorationMeshInstances/" . $instance['class'];
	ensureDirectoryExists($classSubdir);
	$path = realpath($classSubdir) . "/$instanceCount-" . $instance['hash'] . '.json';

	echo "Extracting to $classSubdir/$instanceCount-" . $instance['hash'] . ".json\r\n";
	return $path;
}

/**
 * @param $classSubdir
 */
function ensureDirectoryExists($directory)
{
	if(!realpath($directory))
		mkdir(realpath("./") . '/' . $directory, 0777, TRUE);
}

/**
 * Translate hex code into ascii
 * @param $hex
 * @return string
 */
function hex2str($hex) {
	$str = '';

	//trim any empty 2-byte chunks off the right side
	while(preg_match('/00$/', $hex))
		$hex = substr($hex, 0, -2);

	//get 2-byte chunks, encode decimal, then get ascii
	for($i=0;$i<strlen($hex);$i+=2)
		$str .= chr(hexdec(substr($hex,$i,2)));

	return $str;
}

function resetState() {
	global $instance, $lineCount, $instanceCount;

	$instance = array();
	$lineCount = -1;
	$instanceCount++;
}



loopThroughLevels();