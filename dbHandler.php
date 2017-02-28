<?php
class dbHandler
{

  public static $dbName = 'journey_meshes';
  public static $dbUser = 'username';
  public static $dbPass = 'password';
  public static $dbHost = '127.0.0.1';
  public static $dbPort = '3306';
  public static $dbSock = '/tmp/mysql/mysql.sock';

  /**
   * Opens the database connection. Assumes MySQL.
   */
  public static function connectBySocket()
  {
    /* connect by socket */
    $s = self::$dbSock;
    $n = self::$dbName;
    $dsn = "mysql:unix_socket=$s;dbname=$n";
    return self::getConnection( $dsn );
  }

  public static function connectByHost()
  {
    /* connect by host & port */
    $h = self::$dbHost;
    $p = self::$dbPort;
    $n = self::$dbName;
    $dsn = "mysql:host=$h;port=$p;dbname=$n";
    return self::getConnection( $dsn );
  }

  public static function getConnection( $dsn )
  {
    return new PDO( $dsn, self::$dbUser, self::$dbPass );
  }

}
