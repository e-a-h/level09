<?php
require_once 'Helper.php';
require_once 'LevelProcessor.php';

class ExtractLevels extends LevelProcessor
{
	// state variables
	private $directory;
	private $hash;
	private $class;
	private $instance;
	private $counter;
	private $ReadFile;
	private $line;

	// List of options available to script wizard/cli
	public static $ConfigOptions = array(
		Helper::HelpMultiLevel,
	);

	public function __construct( InvocationOptions $Options )
	{
		parent::__construct( $Options );
	}

	public function handleLevel( string $Level )
	{
		parent::handleLevel( $Level );
		$this->directory = "Level_$this->level";
		$this->loadLevel();
	}

	/**
	 * Open the level's DecorationMeshInstances file and process it
	 */
  private function loadLevel()
	{
		$SourceFileName = "$this->directory/DecorationMeshInstances.lua.bin";
		if( $this->ReadFile = fopen( $SourceFileName, "r" ) )
		{ $this->processFile(); }
		fclose( $this->ReadFile );
	}

	/**
	 * Read the file and extract instances
	 */
  private function processFile()
	{
		//throw away the first line
		$this->line = fread( $this->ReadFile, 16 );

		while( ! feof( $this->ReadFile) )
		{
			$this->line = bin2hex( fread( $this->ReadFile, 16 ) );
			$this->extractInstance();
		}
	}

	/**
	 * Save the line to the global instance. Also saves the hash and class for later use.
	 */
  private function extractInstance()
	{
		$this->extractHash();
		$this->extractClass();
		$this->instance .= $this->line;

		//we're at the end of the block
		if( ( ( $this->counter + 1 ) % 132 ) == 0 )
		{
			// Write the file
			$this->writeFile();
			// Reset for the next instance
			$this->counter = 0;
			$this->counter = 0;
			$this->hash = '';
			$this->class = '';
			$this->instance = '';
		}
		else
		{
			$this->counter++;
		}
	}

	/**
	 * Extract the hash from the DecorationMeshInstance
	 */
  private function extractHash()
	{
		if( ( $this->counter == 7 ) || ( $this->counter == 8 ) )
		{ $this->hash .= $this->line; }
	}

	/**
	 * Extract the class name from the DecorationMeshInstance
	 */
  private function extractClass()
	{
		if( ( $this->counter == 11 ) || ( $this->counter == 12 ) )
		{ $this->class .= $this->line; }
	}

	/**
	 * Write the instance to a file
	 */
  private function writeFile()
	{
		$this->instance = hex2bin( $this->instance );
		$this->class = Helper::hex2str( $this->class );
		$this->hash = Helper::hex2str( $this->hash );
		$path = $this->prepareOutput();

		echo "Extracting $this->directory/$this->class/$this->hash\n";
		file_put_contents( $path, $this->instance );
	}

	/**
	 * Prepare the output file path, and return it
	 * We also
	 * @return string
	 */
  private function prepareOutput()
	{
		$classSubdir = "$this->directory/DecorationMeshInstances/$this->class";
		if( $basepath = Helper::validateDirectory( $classSubdir ) )
		{
			return "$basepath/$this->hash";
		}

		echo "Failed to construct path from $this->directory";
		exit();
	}


}