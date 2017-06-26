<?php
require_once 'LevelProcessor.php';
require_once 'Helper.php';
require_once 'HullInstance.php';
require_once 'dbHandler.php';
require_once 'InvocationOptions.php';

class ExtractHulls extends LevelProcessor
{
  // States
	private $directory;
	private $instanceId = 1;
	private $instance_buffer = array();

	// List of options available to script wizard/cli
  public static $ConfigOptions = array(
		Helper::HelpMultiLevel,
		Helper::HelpDbIn,
		Helper::HelpDbOut,
		Helper::HelpDryRun,
	);

  public function __construct( InvocationOptions $Options )
  {
		parent::__construct( $Options );

    if( ! $this->Invocation->isDryRun() && $this->Invocation->isDbIn() )
    {
      // get autoinc value for hull_instance table for use in db export
      print "\n\nConnecting to database...\n";
      $db = dbHandler::connectByHost();
      $this->instanceId = dbHandler::initAutoIncrement( $db, 'hull_instance' );
    }
	}

  /**
   * Open the level's HullInstances file and process it
	 *
	 * @param string $Level
   */
  public function handleLevel( string $Level )
  {
		$this->level = $Level;
		$this->directory = "Level_$Level";
		$handle = fopen("$this->directory/HullInstances.lua.bin", "r");
    if ( $handle )
    { $this->processFile( $handle ); }
    fclose( $handle );
  }
  
  /**
   * Read the file and extract objects
   *
   * @param $handle
   */
  private function processFile( $handle )
  {
    // Filesize is unsigned long starting at offset 0x4
  
    fseek( $handle, 4 );
    $fsbin = fread( $handle, 4 );
    $filesize = current( unpack( 'N', $fsbin ) );
  
    fseek( $handle, 16 );
  
    // Header start delimiter looks something like
    // 011431A0 00000000 00000000 00000000
    // where
    // 011431   is unknown but constant per file
    //       A0 is header length
    // The rest is zero-padding
    $delimiter = fread( $handle, 16 );
  
    fseek( $handle, 19 );
    $headersize = fread( $handle, 1 );
  
    // seek to start of first instance
    fseek( $handle, 16 );

    if( ! $this->Invocation->isDryRun() )
    { HullInstance::setDirectory( $this->directory ); }

    $Instances = array();
    $NextInstanceOffset = ftell( $handle );
    // The last "NextInstanceOffset" is 0x00000000 and should break this loop
    while( $NextInstanceOffset )
    {
      $NextInstanceOffset = $this->unpackInstance( $handle, $NextInstanceOffset, $Instances );
    }
    Helper::plog("$this->directory Instance count: ".count($Instances));
  }
  
  /// \brief unpack a hull instance
  private function unpackInstance( $handle, $StartOffset, &$Instances )
  {
    // Go to instance start, plus one row
    fseek( $handle, $StartOffset + 16 );
  
    // Count up values of assumed zero-padding to ensure no values are missed
    $Zero = 0;
  
    // get some vectors. what are they? probably transform matrix. noremcstew is teh smrtest
    $a = Helper::unpackFloatVector( fread( $handle, 12 ) );
    $Zero += Helper::extractLong( $handle );
    $b = Helper::unpackFloatVector( fread( $handle, 12 ) );
    $Zero += Helper::extractLong( $handle );
    $c = Helper::unpackFloatVector( fread( $handle, 12 ) );
    $Zero += Helper::extractLong( $handle );
    $d = Helper::unpackFloatVector( fread( $handle, 12 ) );
    $Zero += Helper::extractLong( $handle );
  
    // Array of offsets for face,index,edge,vert
    $Offsets = Helper::unpackFourLong( $handle );
  
    // Array of counts for face,index,edge,vert
    $Counts = Helper::unpackFourLong( $handle );
  
    // Unknown values
    $MysteryLongA = Helper::extractLong( $handle );
    $MysteryLongB = Helper::extractLong( $handle );
    $MysteryFloat = Helper::extractFloat( $handle );
    $Zero += Helper::extractLong( $handle );
  
    // Offset for the beginning of the next instance
    $NextInstanceOffset = Helper::extractLong( $handle );
    $uid = fread( $handle, 32 );

    if( $Zero !== 0 )
    {
      exit( "Unexepcted non-zero value $Zero in instance $uid\n" );
    }

    // array of char
    $Faces = array();
    $FaceOffset = $Offsets[0];
    $FaceCount = $Counts[0];
    fseek( $handle, $FaceOffset );
    $FacesBin = fread( $handle, $FaceCount * 3 );
    $FacesSplit = str_split( $FacesBin, 3 );
    foreach( $FacesSplit as $value )
    {
      $Faces[] = Helper::unpackCharArray( $value );
    }

    // array of char
    $Index = array();
    $IndexOffset = $Offsets[1];
    $IndexCount = $Counts[1];
    fseek( $handle, $IndexOffset );
    $IndexBin = fread( $handle, $IndexCount );
    $IndexSplit = str_split( $IndexBin );
    foreach( $IndexSplit as $value )
    {
      $Index[] = Helper::unpackCharArray( $value );
    }
  
    // array of char
    $Edges = array();
    $EdgeOffset = $Offsets[2];
    $EdgeCount = $Counts[2];
    fseek( $handle, $EdgeOffset );
    $EdgesBin = fread( $handle, $EdgeCount * 2 );
    $EdgesSplit = str_split( $EdgesBin, 2 );
    foreach( $EdgesSplit as $value )
    {
      $Edges[] = Helper::unpackCharArray( $value );
    }
  
    // array of float vector
    $Verts = array();
    $VertOffset = $Offsets[3];
    $VertCount = $Counts[3];
    fseek( $handle, $VertOffset );
    $VertsBin = fread( $handle, $VertCount * 16 );
    $VertsSplit = str_split( $VertsBin, 16 );
    foreach( $VertsSplit as $value )
    {
      $Verts[] = Helper::unpackFloatVector( substr( $value, 0, 12 ) );
    }
  
    // All done. Go to the next instance.
    if( $NextInstanceOffset )
    {
      fseek( $handle, $NextInstanceOffset );
    }
  
    $Instance = array
  (
    "txA" => $a,
    "txB" => $b,
    "txC" => $c,
    "txD" => $d,
    "Offsets" => $Offsets,
    "Counts" => $Counts,
    "mA" => $MysteryLongA,
    "mB" => $MysteryLongB,
    "mC" => $MysteryFloat,
    "uid" => $uid,
    "vertices" => $Verts,
    "faces" => $Faces,
    "edges" => $Edges,
    "index" => $Index,
    "instanceid" => $this->instanceId,
    "nextinstanceoffset" => $NextInstanceOffset,
  );
    
    $Instances[] = $this->processInstance( $Instance );
    return $NextInstanceOffset;
  }

  private function processInstance( $Instance )
  {
    $Hull = new HullInstance( $Instance );
    if( $this->Invocation->isDbIn() )
    {
      print "Next instance ID is $this->instanceId\n";
      $values = $Hull->getMysqlValues();
      Helper::plog( $values, true );
      $this->instance_buffer[] = $values;

      // Process 50 at a time to speed up queries
      if( count( $this->instance_buffer ) == 50 )
      { $this->executeBuffer(); }
    }
    else
    {
      if( $this->Invocation->isDryRun() )
      {
        print "UID: ";
        Helper::plog( $Hull->getUid() );
        print "Next Offset: ";
        Helper::plog( $Hull->getNextInstanceOffset() );
      }
      else
      { $Hull->exportObj(); }
    }
  
    $this->instanceId++;
    return $Hull;
  }

  private function executeBuffer()
  {
    if( ! empty( $this->instance_buffer ) )
    {
      if( $this->Invocation->isDryRun() )
      { Helper::plog( $this->instance_buffer ); }
      else
      { /* sql exec */ }
    }
  }

}
