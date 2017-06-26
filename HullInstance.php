<?php

class HullInstance
{
	private $faces;
	private $edges;
	private $unkownindex;
	private $vertices;
  private $txA;
  private $txB;
	private $txC;
  private $txD;
	private $mA;
	private $mB;
	private $mC;
	private $uid;
	private $hullinstanceid;
	private $nextinstanceoffset;
	static private $directory;
	static private $MelScriptPath;
	static private $HullInstanceDir;
	static private $HullInstanceWinDir;
	// Store offsets?

	public function __construct( array $data )
	{
		$this->setFaces( $data['faces'] );
		$this->setEdges( $data['edges'] );
		$this->setVerts( $data['vertices'] );
		$this->setTransformMatrix( array(
		  array( $data['txA'][0], $data['txA'][1], $data['txA'][2] ),
		  array( $data['txB'][0], $data['txB'][1], $data['txB'][2] ),
		  array( $data['txC'][0], $data['txC'][1], $data['txC'][2] ),
		  array( $data['txD'][0], $data['txD'][1], $data['txD'][2] ),
    ) );
		$this->setUnkownIndex( $data['index'] );
		$this->setSomeOtherShit(
		  array(
				$data['mA'],
				$data['mB'],
				$data['mC'],
			)
		);
		$this->setUID( $data['uid'] );
		$this->setNextInstanceOffset( $data['nextinstanceoffset'] );
	}

	public function getMysqlValues()
  {
    $Result = array(
      'hull_instance' => array(
        'uid' => $this->uid,
        'face_count' => count( $this->faces ),
        'index_count' => count( $this->unkownindex ),
        'edge_count' => count( $this->edges ),
        'vertex_count' => count( $this->vertices ),
        // These vectors are most likely tranform matrix
        'transform_matrix_a' => array( $this->txA[0], $this->txA[1], $this->txA[2] ),
        'transform_matrix_b' => array( $this->txB[0], $this->txB[1], $this->txB[2] ),
        'transform_matrix_c' => array( $this->txC[0], $this->txC[1], $this->txC[2] ),
        'transform_matrix_d' => array( $this->txD[0], $this->txD[1], $this->txD[2] ),
        'prop_a' => $this->mA,// int
        'prop_b' => $this->mB,// int
        'prop_c' => $this->mC,// float
      ),
      'hull_faces' => array(
        'hullinstance_id' => $this->hullinstanceid,
        'face_data' => $this->faces,
      ),
      'hull_polydata' => array(
        'hullinstance_id' => $this->hullinstanceid,
        'poly_data' => $this->unkownindex,
      ),
      'hull_edges' => array(
        'hullinstance_id' => $this->hullinstanceid,
        'edge_data' => $this->edges,
      ),
      'hull_vertices' => array(),
    );

    foreach( $this->vertices as $index => $vert )
    {
      // TODO: change schema so verts table is 1 row per hullinstance?
      // JSON format vertex data
      $newvert = array(
      'hullinstance_id' => $this->hullinstanceid,
        'index' => $index,
        'x' => $vert[0],
        'y' => $vert[1],
        'z' => $vert[2],
      );
      $Result['hull_vertices'][$index] = $newvert;
    }

    return $Result;
  }

	public function exportObj()
	{
		// Do the export
		$filepath = self::$HullInstanceDir . "/$this->uid.obj";
		file_put_contents( $filepath, $this->getObjExport() );

		// TODO: fix this and save import script
		$ImportLine = 'file -import -type "OBJ"  -ignoreVersion -ra true ' .
		              '-mergeNamespacesOnClash false -options "mo=1" -pr "' .
		              self::$HullInstanceWinDir .
		              "/$this->uid.obj" .
		              '";';

		file_put_contents( self::$MelScriptPath, "$ImportLine\r\n", FILE_APPEND );
	}

	public function getObjExport()
	{
		ob_start();
		foreach( $this->vertices as $index => $v )
		{
			print "v $v[0] $v[1] $v[2]\r\n";
		}
		
		foreach( $this->faces as $index => $f )
		{
			foreach( $f as $i=>$v )
			{
				// Stored verts are 0-indexed. Convert to 1-index
				$f[$i]=++$v;
			}
			print "f " . implode( ' ', $f ) . "\r\n";
		}

		// foreach( $this->edges as $index => $e )
		// {
		// 	print "e $e[0] $e[1]\r\n";
		// }
		
		$Obj = ob_get_contents();
		ob_end_clean();
		return $Obj;
	}

	static public function setDirectory( string $directory )
	{
		self::$directory = $directory;
		if( self::$HullInstanceDir = Helper::validateDirectory( self::$directory . "/HullInstances", true ) )
		{
			// Get the Windows path to hull extract dir
			$exportpath = shell_exec( "cygpath -am \"" . self::$HullInstanceDir . "\"" );
			// strip newline char(s) and add mel script file name
			self::$HullInstanceWinDir = preg_replace( "/[\r\n]*/", "", $exportpath );

			self::$MelScriptPath = self::$HullInstanceDir . "/Import_All_Hulls.mel";

      if( file_exists( self::$MelScriptPath ) )
      {
        // Delete MEL script file so we don't append to it repeatedly
        unlink( self::$MelScriptPath );
      }
		}
		else
		{
			echo "Failed to construct path from " . self::$directory . "\n";
		}
	}

	public function setFaces( array $data )
	{
		if( ! empty( $data ) ) { $this->faces = $data; }
	}

	public function setEdges( array $data )
	{
		if( ! empty( $data ) ) { $this->edges = $data; }
	}

	public function setUnkownIndex( array $data )
	{
		if( ! empty( $data ) ) { $this->unkownindex = $data; }
	}

	public function setVerts( array $data )
	{
		if( ! empty( $data ) ) { $this->vertices = $data; }
	}
	
	public function setTransformMatrix( array $data )
	{
		// validate
		if( count( $data ) !== 4 )
		{
		  foreach( $data as $vector )
      {
        if( ! is_array( $vector ) || ( count( $vector ) !== 3 ) )
        {
          return; // fail
        }
      }
		}
    $this->txA = $data[0];
    $this->txB = $data[1];
    $this->txC = $data[2];
    $this->txD = $data[3];
	}

	public function setSomeOtherShit( array $data )
	{
		// validate
		if( count( $data ) == 3 )
		{
			$this->mA = $data[0];
			$this->mB = $data[1];
			$this->mC = $data[2];
		}
	}

	public function setUID( string $UID )
	{
		if( ! empty( $UID ) && ( preg_match( '/[a-fA-F0-9]{32}/', $UID ) ) )
		{ $this->uid = $UID; }
	}

	public function setHullInstanceId( int $HullInstanceId )
	{
		if( ! empty( $HullInstanceId ) )
		{ $this->hullinstanceid = $HullInstanceId; }
	}

	public function setNextInstanceOffset( int $Offset )
	{
		if( ! empty( $Offset ) )
		{ $this->nextinstanceoffset = $Offset; }
	}

	public function getUid()
  {
    return $this->uid;
  }

  public function getNextInstanceOffset()
  {
    return $this->nextinstanceoffset;
  }

}
