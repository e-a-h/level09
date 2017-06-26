<?php
require_once 'Helper.php';
require_once 'dbCredentials.php';

class dbHandler
{
	/**
	 * Opens the database connection. Assumes MySQL.
	 */
	public static function connectBySocket()
	{
		/* connect by socket */
		$s = dbCredentials::$dbSock;
		$n = dbCredentials::$dbName;
		$dsn = "mysql:unix_socket=$s;dbname=$n";
		return self::getConnection( $dsn );
	}

	public static function connectByHost()
	{
		/* connect by host & port */
		$h = dbCredentials::$dbHost;
		$p = dbCredentials::$dbPort;
		$n = dbCredentials::$dbName;
		$dsn = "mysql:host=$h;port=$p;dbname=$n";
		return self::getConnection( $dsn );
	}

	public static function getConnection( $dsn )
	{
		try
		{
			return new PDO( $dsn, dbCredentials::$dbUser, dbCredentials::$dbPass );
		}
		catch( Exception $e )
		{
			exit( "Sorry, shit's busted: " . $e->getMessage() . "\n" );
		}
	}

	public static function initAutoIncrement( PDO $db, string $table )
	{
		$autoinc_tables = array(
		  'mesh_instances',
		  'mesh_instance_properties',
		  'hull_instance',
		  'hull_faces',
		  'hull_polydata',
		  'hull_edges',
		  'hull_verticies',
		);

		// Strict matching of table name. You know, for safety!
		if( in_array( $table, $autoinc_tables, true ) )
		{
			$sql = "SELECT `AUTO_INCREMENT` "
			     . "FROM INFORMATION_SCHEMA.TABLES "
			     . "WHERE TABLE_SCHEMA = 'journey_meshes' "
			     . "AND TABLE_NAME = '$table';";
			$query = $db->prepare( $sql );
			$query->execute();
			$result = $query->fetch();
		}
		if( $result !== false )
		{ return intval( $result[0] ); }
		else
		{ exit( "Failed to find auto_increment value in db.table: $table\n" ); }
	}

}
