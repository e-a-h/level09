<?php
require_once 'Helper.php';
require_once 'LevelProcessor.php';
require_once 'InvocationOptions.php';

class RebuildDecorationMeshInstances extends LevelProcessor
{
	private $OutputPath;
	private $ArchivePath;
	private $BackupDirectory;
	private $WriteFile;
	private $Instance;

	// List of options available to script wizard/cli
	public static $ConfigOptions = array(
		Helper::HelpMultiLevel,
		Helper::HelpDryRun,
	);

	public function __construct( InvocationOptions $Options )
	{
		parent::__construct( $Options );

		// this could take a while...
		ini_set( "memory_limit", "-1" );
		set_time_limit( 0 );
		// used for backup filenames
		date_default_timezone_set( "America/Chicago" );
	}

	public function handleLevel( string $Level )
	{
		parent::handleLevel( $Level );
		$this->OutputPath = "Level_$this->level/DecorationMeshInstances.lua.bin";
		$this->ArchivePath = "Level_$this->level/DecorationMeshInstances/";
		$this->BackupDirectory = "Level_$this->level/Backups";
		$this->backupLevel();
		$this->rebuildLevel();
	}

		/**
	 * Backup the output file before writing. Don't lose good work.
	 */
	private function backupLevel()
	{
		if( realpath( $this->OutputPath ) )
		{
			$this->ensureDirectoryExists( $this->BackupDirectory );
			rename( $this->OutputPath, $this->getBackupPath( $this->BackupDirectory ) );
		}
	}

	/**
	 * Get the path to the backup directory
	 *
	 * @param string $directory
	 *
	 * @return string
	 */
	private function getBackupPath( $directory )
	{
		return "$directory/DecorationMeshInstances " . date( 'Ymd_h-i-s' ) . ".lua.bin";
	}

	/**
	 * Create the directory if it doesn't exist
	 *
	 * @param $directory
	 */
	private function ensureDirectoryExists( $directory )
	{
		$TargetDirName = realpath( "./" ) . '/' . $directory;
		if( ( ! realpath( $directory ) ) &&
			  ( ! mkdir( $TargetDirName, 0777, true ) ) )
		{
			;
		}
	}

	/**
	 * Rebuild the level's DecorationMeshInstances.lua.bin file from our JSON files
	 */
	private function rebuildLevel()
	{
		echo "Rebuilding " . $this->OutputPath . PHP_EOL;

		$InstanceFiles = $this->orderInstances();
		$this->WriteFile = fopen( $this->OutputPath, "w" );
		fwrite( $this->WriteFile, $this->getHeader( sizeof( $InstanceFiles ) ) );

		foreach( $InstanceFiles as $InstanceFile )
		{
			$this->Instance = json_decode( file_get_contents( $InstanceFile ) );

			if( ! $this->Instance || empty( $this->Instance ) )
			{
				var_dump( $InstanceFile );
				die( 'failed to decode json!' );
			}
			$this->processInstance();
		}

		fclose( $this->WriteFile );
	}

	/**
	 * Organize our instances to restore the original order
	 *
	 * @return array
	 */
	private function orderInstances() : array
	{
		$InstanceFiles = array();

		foreach( Helper::getDirectoryContents( $this->ArchivePath ) as $class )
		{
			foreach( Helper::getDirectoryContents( $this->ArchivePath . $class ) as $filename )
			{
				if( $this->isJsonFile( $filename ) )
				{
					$index = substr( $filename, 0, strpos( $filename, '-' ) );
					$InstanceFiles[$index] = $this->ArchivePath . "$class/$filename";
				}
			}
		}

		ksort( $InstanceFiles );
		return $InstanceFiles;
	}

	/**
	 * Generate the file's header line, which includes a count of instances in the level
	 *
	 * @param $instanceCount
	 *
	 * @return string
	 */
	private function getHeader( $instanceCount )
	{
		$instanceCount = str_pad( dechex( $instanceCount ), 4, '0', STR_PAD_LEFT );
		$header = hex2bin( "000000010000" );
		$header .= hex2bin( $instanceCount );
		$header .= hex2bin( Helper::pad( 4 ) );

		return $header;
	}

	/**
	 * Check if a file is one of our JSON-encoded instances
	 *
	 * @param $filename
	 *
	 * @return string
	 */
	private function isJsonFile( $filename )
	{
		return stristr( $filename, '.json' );
	}

	/**
	 * Handle the read file resource
	 */
	private function processInstance()
	{
		$instanceBin = $this->rebuildInstance( $this->Instance );
		fwrite( $this->WriteFile, $instanceBin );
	}

	/**
	 * Compile the instance into binary
	 *
	 * @param $instance
	 *
	 * @return string
	 */
	private function rebuildInstance( $instance )
	{
		$instanceBin = hex2bin( $instance->header . Helper::pad( 6 ) );
		$instanceBin .= Helper::floatTuple2Bin( $instance->meta1 );
		$instanceBin .= Helper::floatTuple2Bin( $instance->meta2 );
		$instanceBin .= Helper::floatTuple2Bin( $instance->meta3 );
		$instanceBin .= Helper::floatTuple2Bin( $instance->position, false );
		$instanceBin .= hex2bin( "3f800000" );
		$instanceBin .= Helper::floatTuple2Bin( $instance->data1 );
		$instanceBin .= Helper::floatTuple2Bin( $instance->data2, false );
		$instanceBin .= hex2bin( Helper::pad( 1 ) . $instance->flag );
		$instanceBin .= Helper::str2bin( $instance->hash );
		$instanceBin .= Helper::str2bin( $instance->class );
		$instanceBin .= Helper::str2bin( $instance->render );
		$instanceBin .= $this->rebuildProperties( $instance->properties );

		return $instanceBin;
	}

	/**
	 * Rebuild binary for the instance properties. Return binary string and count
	 *
	 * @param $properties
	 *
	 * @return string
	 */
	private function rebuildProperties( $properties ) : string
	{
		$propertiesBin = '';
		$count = 0;

		foreach( (array) $properties as $property => $data )
		{
			$propertiesBin .= $this->rebuildProperty( $property, $data );
			$count++;
		}

		// pad the remaining space
		$padding = 16 - $count;
		while( $padding > 0 )
		{
			$propertiesBin .= hex2bin( Helper::pad( 56 ) );
			$padding--;
		}

		// add the property count based on what we've processed,
		// rather than using the value listed in the instance
		$hexCount = str_pad( dechex( $count ), 4, '0', STR_PAD_LEFT );
		$propertiesBin .= hex2bin( Helper::pad( 1 ) . $hexCount . Helper::pad( 6 ) );

		return $propertiesBin;
	}

	/**
	 * Rebuild binary representation of an instance property
	 *
	 * @param $property
	 * @param $data
	 *
	 * @return string
	 */
	private function rebuildProperty( $property, $data ) : string
	{
		$propertyBin = Helper::floatTuple2Bin( $data->data );
		$propertyBin .= Helper::str2bin( $data->texture );
		$propertyBin .= Helper::str2bin( $property, 1 );
		$propertyBin .= hex2bin( Helper::pad( 1 ) . $data->flag . Helper::pad( 6 ) );

		return $propertyBin;
	}

}
