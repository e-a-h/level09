<?php
require_once 'Helper.php';
require_once 'LevelProcessor.php';
require_once 'dbHandler.php';
require_once 'InvocationOptions.php';

class ExtractJson extends LevelProcessor
{

	/*      state variables     */
	// stores our current decoration mesh until we're ready to write to file
	private $instance = array();
	// line counter, to know what data we're looking at, and when to write to file
	private $lineCount = 0;
	// count how many instances we've processed, used as index when rebuilding
	private $instanceCount = 0;
	private $handle;
	private $directory;

	// List of options available to script wizard/cli
	public static $ConfigOptions = array(
		Helper::HelpMultiLevel,
	);

	public function __construct( InvocationOptions $Invocation )
	{
		parent::__construct( $Invocation );
		// this could take a while...
		ini_set( "memory_limit", "-1" );
		set_time_limit( 0 );
	}

	/**
	 * Scan the DecorationMeshInstances directory for the current level,
	 * and prepare the write file
	 *
	 * @param $Level
	 */
	public function handleLevel( string $Level )
	{
		parent::handleLevel( $Level );
		$this->instance = array();
		$this->lineCount = 0;
		$this->instanceCount = 0;
		$this->directory = "Level_$this->level";
		$this->handleFile();
	}

	/**
	 * Open the level's DecorationMeshInstances file and process it
	 */
	private function handleFile()
	{
		$this->handle = fopen( "$this->directory/DecorationMeshInstances.lua.bin", "r" );
		if( $this->handle )
		{ $this->processFile(); }
		fclose( $this->handle );
	}

	/**
	 * Read the file and extract objects
	 */
	private function processFile()
	{
		//throw away the first line
		$line = fread( $this->handle, 16 );

		while( ! feof( $this->handle ) )
		{
			$line = fread( $this->handle, 16 );
			$this->extractObject( $line );
		}
	}

	/**
	 * Save the line to the global object. Also saves the hash and class for later use.
	 *
	 * @param int $line
	 */
	private function extractObject( $line )
	{
		$this->extractObjectData( $line );

		//we're at the end of the block
		if( ( $this->lineCount + 1 ) % 132 == 0 )
		{
			$this->formatProperties();
			$this->writeFile();
		}

		$this->lineCount++;
	}

	private function extractObjectData( $line )
	{
		// these lines should be decoded to hex
		if( ( $this->lineCount == 0 ) || ( $this->lineCount >= 7 ) )
		{
			$line = bin2hex( $line );
		}

		switch( $this->lineCount )
		{
			case 0:
				$this->instance['header'] = substr( $line, 0, 8 );
				break;
			case 1:
				$this->instance['meta1'] = Helper::unpackStringOfFloats( substr( $line, 0, 12 ) );
				break;
			case 2:
				$this->instance['meta2'] = Helper::unpackStringOfFloats( substr( $line, 0, 12 ) );
				break;
			case 3:
				$this->instance['meta3'] = Helper::unpackStringOfFloats( substr( $line, 0, 12 ) );
				break;
			case 4:
				$this->instance['position'] = Helper::unpackStringOfFloats( substr( $line, 0, 12 ) );
				break;
			case 5:
				$this->instance['data1'] = Helper::unpackStringOfFloats( $line );
				break;
			case 6:
				$this->instance['data2'] = Helper::unpackStringOfFloats( substr( $line, 0, 12 ) );
				$this->instance['flag'] = bin2hex( substr( $line, 14, 4 ) );
				break;
			case 7:
				// initialize
				$this->instance['hash'] = $this->extractValue( $line );
				break;
			case 8:
				$this->instance['hash'] .= $this->extractValue( $line );
				break;
			case 9:
				break;
			case 10:
				break;
			case 11:
				// initialize
				$this->instance['class'] = $this->extractValue( $line );
				break;
			case 12:
			case 13:
				$this->instance['class'] .= $this->extractValue( $line );
				break;
			case 14:
				break;
			case 15:
				// initialize
				$this->instance['render'] = $this->extractValue( $line );
				break;
			case 16:
				$this->instance['render'] .= $this->extractValue( $line );
				break;
			case 18:
				// initialize
				$this->instance['properties'] = '';
				break;
			case 131:
				$this->instance['propertyCount'] = substr( $line, 4, 4 );
				break;
			default:
				break;
		}

		//gather property list to parse later
		if( $this->lineCount > 18 && $this->lineCount < 131 )
		{
			$this->instance['properties'] .= $line . '|';
		}
	}

	/**
	 * Extract value from a line, preferring ascii over float interpretation
	 * You can pass 'true' as the second parameter to force conversion to float
	 * If we do translate floats, include the final byte group if not empty
	 *
	 * @param $line
	 * @param bool $avoidAscii
	 *
	 * @return string
	 */
	private function extractValue( $line, $avoidAscii = false )
	{
		$enc = mb_check_encoding( Helper::hex2str( $line ), 'ASCII' );
		if( ( ! $avoidAscii ) && $enc )
		{
			return Helper::hex2str( $line );
		}

		$line = hex2bin( $line );
		$line = substr( $line, 12, 4 ) ? $line : substr( $line, 0, 12 );

		return Helper::unpackStringOfFloats( $line );
	}

	private function formatProperties()
	{
		$propArray = explode( '|', rtrim( $this->instance['properties'], '|' ) );
		$this->instance['properties'] = array();

		for( $i = 0; $i < 16; $i++ )
		{
			$this->formatProperty( $i, $propArray );
		}
	}

	/**
	 * @param $i
	 * @param $propArray
	 */
	private function formatProperty( $i, $propArray )
	{
		$base = $i * 7;
		$propertyName = $this->extractValue( $propArray[$base + 5] );

		if( $propertyName )
		{
			$propertyData = $this->extractValue( substr( $propArray[$base], 0, 24 ), true );
			$propertyTexture = $this->extractValue( $propArray[$base + 1] . $propArray[$base + 2] );
			$propertyFlag = substr( $propArray[$base + 6], 4, 4 );

			$this->instance['properties'][$propertyName] = array(
				'flag' => $propertyFlag,
				'data' => $propertyData,
				'texture' => $propertyTexture
			);
		}
	}

	/**
	 * Write the object to a file
	 */
	private function writeFile()
	{
		$path = $this->prepareOutput();
		file_put_contents( $path, json_encode( $this->instance ) );
		$this->resetState();
	}

	/**
	 * Prepare the output file path, and return it
	 * We also
	 * @return string
	 */
	private function prepareOutput()
	{
		$classSubdir = "$this->directory/DecorationMeshInstances/" . $this->instance['class'];
		if( $basepath = Helper::validateDirectory( $classSubdir ) )
		{
			$path = "$basepath/$this->instanceCount-" . $this->instance['hash'] . '.json';
			echo "Extracting to $classSubdir/$this->instanceCount-" . $this->instance['hash'] . ".json\r\n";
			return $path;
		}

		exit( "Failed to construct path from $this->directory" );
	}

	private function resetState()
	{
		$this->instance = array();
		$this->lineCount = -1;
		$this->instanceCount++;
	}

}