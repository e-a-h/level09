<?php
require_once 'Helper.php';
require_once 'LevelProcessor.php';
require_once 'dbHandler.php';
require_once 'InvocationOptions.php';

class PlotPositions extends LevelProcessor
{
	const OutFileBaseName = "meshinstance";

	private $directory;
	private $InstanceClass;
	private $WriteFile;
	private $ReadFile;

  private $minx = 9999;
  private $miny = 9999;
  private $minz = 9999;
  private $maxx = -9999;
  private $maxy = -9999;
  private $maxz = -9999;

	// color of plot point (int), line count
	private $color = 0;
	private $counter = 0;

	// what class names do you want?
	// uses preg_match, so you can get partial matches
	private $search_keys = array( '.*' );
	// $search_keys = array('Rock', 'Flag', 'Tapestry');

	// List of options available to script wizard/cli
	public static $ConfigOptions = array(
		Helper::HelpMel,
		Helper::HelpMultiLevel
	);

	/**
	 * PlotPositions constructor.
	 *
	 * @param $Invocation
	 */
	public function __construct( InvocationOptions $Invocation )
	{
		parent::__construct( $Invocation );
	}

	/**
	 * Scan the DecorationMeshInstances directory for the current level,
	 * and prepare the write file
	 *
	 * @param string $Level
	 */
	public function handleLevel( string $Level )
	{
		parent::handleLevel( $Level );
		print "Level is now $this->level\n";

		// Initiate for this level
		$this->level = $Level;
		$this->color = 0;
		$this->minx = 9999;
		$this->miny = 9999;
		$this->minz = 9999;
		$this->maxx = -9999;
		$this->maxy = -9999;
		$this->maxz = -9999;

		$classes = $this->getDirectoryContents( "Level_$this->level/DecorationMeshInstances" );
		$Ext = $this->Invocation->isMelExport() ? 'mel' : 'txt';
		$this->WriteFile = fopen(
			"Level_$this->level/" . self::OutFileBaseName . "-positions.$Ext",
			"w"
		);

		foreach( $this->search_keys as $key )
		{
			foreach( $classes as $class )
			{
				$this->InstanceClass = $class;
				$this->processClass( $key, $class );
			}
		}

		// add north/south/east/west labels using point cloud bounds
		$avx = ( $this->minx + $this->maxx ) / 2;
		$avy = ( $this->miny + $this->maxy ) / 2;

		$this->InstanceClass = "north";
		$this->writePosition( $this->maxx, $avy, 0, "north", 0 );
		$this->InstanceClass = "south";
		$this->writePosition( $this->minx, $avy, 0, "south", 0 );
		$this->InstanceClass = "east";
		$this->writePosition( $avx, $this->miny, 0, "east", 0 );
		$this->InstanceClass = "west";
		$this->writePosition( $avx, $this->maxy, 0, "west", 0 );

		fclose( $this->WriteFile );
		/*
		After this, you can plot in gnuplot using:
		gnuplot> set xlabel "x axis"; set ylabel "y axis"; set zlabel "z axis"; set view equal xyz
		gnuplot> splot 'Level_Barrens/rock-flag-positions.txt' u 1:2:3:4:5 w labels tc palette offset 0,-1 point palette
	 */
	}

	// output a line to the output file

	/**
	 * @param $x
	 * @param $y
	 * @param $z
	 * @param string $label
	 * @param int $index
	 */
	private function writePosition( $x, $y, $z, string $label = "", int $index = 0 )
	{
		if( $this->Invocation->isMelExport() )
		{
			fwrite( $this->WriteFile, "spaceLocator -p $x $y $z" );
			if( ! empty( $label ) )
			{
				fwrite( $this->WriteFile, " -n \"$label\";" );
			}
			fwrite( $this->WriteFile, "\r\n" );
			print "Added locator $this->InstanceClass to script\n";
		}
		else
		{
			fwrite( $this->WriteFile, "$x $y $z $label $index\r\n" );
			print "Added $this->InstanceClass to output\n";
		}
	}


	/**
	 * This skips the first 2 lines of output (current & parent directory)
	 *
	 * @param $directory
	 *
	 * @return array
	 */
	private function getDirectoryContents( string $directory ) : array
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
	 */
	private function processClass( string $key, string $class )
	{
		print "Class: $class\n";
		if( preg_match( "/$key/", $class ) )
		{
			$this->directory = "Level_$this->level/DecorationMeshInstances/$class";
			$this->color = ++$this->color;
			// $this->setColor($class);
			$this->processFiles();
		}
	}

	/**
	 * Get files from the directory, and output their object positions to the writefile.
	 */
	private function processFiles()
	{
		$files = $this->getDirectoryContents( $this->directory );

		foreach( $files as $filename )
		{
			// Do not operate on files with dot "." in their name, e.g. "something.json"
			// Assumption is that decorationmeshinstance file exists and has no extension.
			if( ! stristr( $filename, '.' ) )
			{ $this->handleFile( $filename ); }
		}
	}

	/**
	 * Open the file for reading and reset line counter
	 *
	 * @param $filename
	 */
	private function handleFile( string $filename )
	{
		$this->counter = 0;
		$this->ReadFile = fopen( "$this->directory/$filename", "rb" );

		// print "$directory/$file\n";
		if( $this->ReadFile && $this->WriteFile )
		{ $this->processFile(); }
		else
		{ print "Something went wrong...\n"; }

		fclose( $this->ReadFile );
	}

	/**
	 * Read each line of the file, translate to hex, and extract position
	 */
	private function processFile()
	{
		while( ! feof( $this->ReadFile ) )
		{
			$binline = fread( $this->ReadFile, 16 );
			$this->extractPosition( $binline );
			$this->counter++;
		}
	}

	/**
	 * Extract the position from the hex provided, and write to output file
	 *
	 * @param $line
	 *
	 */
	private function extractPosition( $line )
	{
		if( $this->counter == 4 )
		{
			// The order coordinates appear in the hex is y,z,x
			// Format to floats
			$y = Helper::unpackFloat( substr( $line, 0, 4 ) );
			$z = Helper::unpackFloat( substr( $line, 4, 4 ) );
			$x = Helper::unpackFloat( substr( $line, 8, 4 ) );

			$this->setBounds( $x, $y, $z );

			// output the coords and color index;
			$Label = preg_replace( '/P_/', '', $this->InstanceClass );
			$Label = preg_replace( '/C_/', '', $Label );
			$Label = preg_replace( '/_/', '-', $Label );

			$this->writePosition( $x, $y, $z, $Label, $this->color );
		}
	}

	/**
	 * push min and max xyz bounds accoring to input. Bounds are cumulative
	 *
	 * @param $x
	 * @param $y
	 * @param $z
	 */
	private function setBounds( $x, $y, $z )
	{
		if( $x > $this->maxx )
		{ $this->maxx = $x; }

		if( $y > $this->maxy )
		{ $this->maxy = $y; }

		if( $z > $this->maxz )
		{ $this->maxz = $z; }

		if( $x < $this->minx )
		{ $this->minx = $x; }

		if( $y < $this->miny )
		{ $this->miny = $y; }

		if( $z < $this->minz )
		{ $this->minz = $z; }
	}

}
