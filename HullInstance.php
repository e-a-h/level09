<?php

class HullInstance
{
	private $faces;
	private $edges;
	private $vertices;
	private $posX;
	private $posY;
	private $posZ;
	private $mA;
	private $mB;
	private $mC;
	private $uid;
	static private $directory;
	static private $MelScriptPath;
	static private $HullInstanceDir;
	static private $HullInstanceWinDir;
	// Store offsets?

	public function __construct( $data )
	{
		$this->setFaces( $data['faces'] );
		$this->setEdges( $data['edges'] );
		$this->setVerts( $data['vertices'] );
		$this->setPosXYZ( array( $data['posX'], $data['posY'], $data['posZ'] ) );
		$this->setSomeOtherShit(
		  array(
				$data['mA'],
				$data['mB'],
				$data['mC'],
			)
		);
		$this->setUID( $data['uid'] );
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

	static public function setDirectory( $directory )
	{
		self::$directory = $directory;
		if( self::$HullInstanceDir = Helper::validateDirectory( self::$directory . "/HullInstances", true ) )
		{
			// Get the Windows path to hull extract dir
			$exportpath = shell_exec( "cygpath -am \"" . self::$HullInstanceDir . "\"" );
			// strip newline char(s) and add mel script file name
			self::$HullInstanceWinDir = preg_replace( "/[\r\n]*/", "", $exportpath );

			self::$MelScriptPath = self::$HullInstanceDir . "/Import_All_Hulls.mel";

			// Delete MEL script file so we don't append to it repeatedly
			unlink( self::$MelScriptPath );
		}
		else
		{
			echo "Failed to construct path from " . self::$directory . "\n";
		}
	}

	public function setFaces( $data )
	{
		// validate
		if( is_array($data) && ! empty( $data ) )
		{
			$this->faces = $data;
		}
	}

	public function setEdges( $data )
	{
		// validate
		if( is_array($data) && ! empty( $data ) )
		{
			$this->edges = $data;
		}
	}

	public function setVerts( $data )
	{
		// validate
		if( is_array($data) && ! empty( $data ) )
		{
			$this->vertices = $data;
		}
	}
	
	public function setPosXYZ( $data )
	{
		// validate
		if( is_array($data) && ( count( $data ) == 3 ) )
		{
			$this->posX = $data[0];
			$this->posY = $data[1];
			$this->posZ = $data[2];
		}
	}

	public function setSomeOtherShit( $data )
	{
		// validate
		if( is_array($data) && ( count( $data ) == 3 ) )
		{
			$this->mA = $data[0];
			$this->mB = $data[1];
			$this->mC = $data[2];
		}
	}

	public function setUID( $UID )
	{
		// print "$UID"
		if( ! empty( $UID ) && ( preg_match( '/[a-fA-F0-9]{32}/', $UID ) ) )
		{ $this->uid = $UID; }
	}

}
