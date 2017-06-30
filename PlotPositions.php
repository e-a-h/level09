<?php
require_once 'Helper.php';
require_once 'LevelProcessor.php';
require_once 'dbHandler.php';
require_once 'InvocationOptions.php';


/**
 * After running this script, you can plot in gnuplot using:
 * gnuplot> set xlabel "x axis"; set ylabel "y axis"; set zlabel "z axis"; set view equal xyz
 * gnuplot> splot 'Level_Barrens/meshinstance-positions.txt' u 1:2:3:4:5 w labels tc palette offset 0,-1 point palette
 */
class PlotPositions extends LevelProcessor
{
	const OutFileBaseName = "meshinstance";

	/** CONFIG OPTIONS **/

	// color of plot point (int)
	private $color = 0;

	// what class names do you want? uses preg_match, so you can get partial matches
	// $search_keys = array('Rock', 'Flag', 'Tapestry');
	private $search_keys = array( '.*' );

	// define a color index for the class plot. uses preg_match, so you can get partial matches
	// if the array is empty, then we will increment for each plot point
	private $colorKeys = array(
	  // 'FlagLg'                  => 0,
	  // 'FlagMesh'                => 1,
	  // 'P_MeshTapestry'          => 2,
	  // 'P_Tapestry2'             => 3,
	  // 'P_BarrensRiverRock'      => 4,
	  // 'P_BarrensTunnelRockWall' => 5,
	  // 'P_M6Rock'                => 6,
	  // 'P_MountainRockB'         => 7,
	  // 'P_RockBackWall'          => 8,
	  // 'RockBed'                 => 9,
	);

	/** END CONFIG OPTIOINS **/

	private $directory;
	private $Label;
	private $WriteFile;

	private $minx = 9999;
	private $miny = 9999;
	private $minz = 9999;
	private $maxx = -9999;
	private $maxy = -9999;
	private $maxz = -9999;

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

		$this->resetState( $Level );
		$this->prepareOutputFile();
		$this->processLevel();
		$this->addCompass();

		fclose( $this->WriteFile );
	}

	// private methods

	/**
	 * Initiate for this level
	 */
	private function resetState( string $Level )
	{
		$this->level = $Level;
		$this->color = 0;
		$this->minx = 9999;
		$this->miny = 9999;
		$this->minz = 9999;
		$this->maxx = -9999;
		$this->maxy = -9999;
		$this->maxz = -9999;
	}

	/**
	 * Generate the output file name, and open it for writing
	 */
	private function prepareOutputFile()
	{
		$extension = $this->Invocation->isMelExport() ? 'mel' : 'txt';
		$filename = "Level_$this->level/" . self::OutFileBaseName . "-positions.$extension";
		$this->WriteFile = fopen( $filename, 'w' );

		if( ! $this->WriteFile) {
			print "Could not open output file $filename \n";
			exit;
		}
	}

	/**
	 * Iterate through all decoration mesh instances subdirectories and process them
	 * 
	 * @todo can we avoid nested foreach here?
	 */
	private function processLevel()
	{
		$classes = $this->getDirectoryContents( "Level_$this->level/DecorationMeshInstances" );

		foreach( $this->search_keys as $key ) {
			foreach( $classes as $class ) {
				$this->processClass( $key, $class );
			}
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

		if( empty( $contents ) ) {
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
		if( ! stristr($class, '.') && preg_match( "/$key/", $class ) ) {
			print "Class: $class\n";

			$this->Label = $class;
			$this->directory = "Level_$this->level/DecorationMeshInstances/$class";

			// determine the color before we format the label
			$this->setColor();
			$this->formatLabel();
			$this->processFiles();
		}
	}
	
	/**
	 * Set the color for this plot point based on its label
	 */
	private function setColor()
	{
		if( empty( $this->colorKeys ) ) {
			return $this->color++;
		}

		$this->color = 0;
		foreach( $colorKeys as $key => $value ) {
			if( preg_match("/$key/", $this->Label ) ) {
				$this->color = $value;
			}
		}
	}

	/**
	 * Remove extraneous characters from the label, and fix formatting for Maya
	 */
	private function formatLabel()
	{
		$this->Label = preg_replace( '/P_/', '', $this->Label );
		$this->Label = preg_replace( '/C_/', '', $this->Label );
		$this->Label = preg_replace( '/_/', '-', $this->Label );
	}

	/**
	 * Get files from the directory, and output their object positions to the writefile.
	 */
	private function processFiles()
	{
		$files = $this->getDirectoryContents( $this->directory );

		foreach( $files as $filename ) {
			if( stristr( $filename, '.json' ) ) {
				$this->handleFile( "$this->directory/$filename" );
			}
		}
	}

	/**
	 * Make sure we can open the input file
	 *
	 * @param $filepath
	 */
	private function handleFile( string $filepath )
	{
		if( file_exists( "$filepath" ) ) {
			$this->processFile( $filepath );
		} else {
			print "Could not the input file $filepath, skipping.\n";
		}
	}

	/**
	 * Read each line of the file, translate to hex, and extract position
	 */
	private function processFile( string $filepath )
	{
		$instance = json_decode( file_get_contents( $filepath ) );

        if( ! isset( $instance->position ) ) {
            return;
        }

        list( $x, $y, $z ) = $this->getCoordinates($instance->position);
		$this->setBounds( $x, $y, $z );
		$this->writePosition( $x, $y, $z, $this->color );
	}

    /**
     * Get XYZ coordinates from instance object, and return them as floats
     */
    private function getCoordinates( string $position )
    {
        list( $x, $y, $z ) = explode(' ', $position);

        return array( (float) $x, (float) $y, (float) $z );
    }

	/**
	 * Push min and max xyz bounds according to input. Bounds are cumulative
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
		{ $this->minx = (float) $x; }

		if( $y < $this->miny )
		{ $this->miny = $y; }

		if( $z < $this->minz )
		{ $this->minz = $z; }
	}

	/**
	 * Output a line to the output file
	 *
	 * @param $x
	 * @param $y
	 * @param $z
	 * @param int $index
	 */
	private function writePosition( $x, $y, $z, int $color = 0 )
	{
		if( $this->Invocation->isMelExport() ) {
			$this->writeMelPosition( $x, $y, $z );
		} else {
			$this->writeGnuplotPosition( $x, $y, $z, $color );
		}
	}

	/**
	 * Write a Mel-formatted position to the output file
	 */
	private function writeMelPosition( $x, $y, $z )
	{
		fwrite( $this->WriteFile, "spaceLocator -p $x $y $z" );
		fwrite( $this->WriteFile, " -n \"$this->Label\";" );
		fwrite( $this->WriteFile, "\r\n" );
		print "Added locator $this->Label to script\n";
	}

	/**
	 * Write a Gnuplot-formatted position to the output file
	 */
	private function writeGnuplotPosition( $x, $y, $z, $color )
	{
		fwrite( $this->WriteFile, "$x $y $z $this->Label $color\r\n" );
		print "Added $this->Label to output\n";
	}

	/**
	 * Add north, south, east and west at the boundaries of the objects being plotted
	 */
	private function addCompass()
	{
		$avx = ( $this->minx + $this->maxx ) / 2;
		$avy = ( $this->miny + $this->maxy ) / 2;

		$this->Label = "north";
		$this->writePosition( $this->maxx, $avy, 0, 0 );

		$this->Label = "south";
		$this->writePosition( $this->minx, $avy, 0, 0 );

		$this->Label = "east";
		$this->writePosition( $avx, $this->miny, 0, 0 );

		$this->Label = "west";
		$this->writePosition( $avx, $this->maxy, 0, 0 );
	}
}
