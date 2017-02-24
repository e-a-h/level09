<?php

class Helper
{
  
  public static function plog( $str )
  {
    print "$str\r\n";
  }

  public static function machineIsLittleEndian()
  {
    $testint = 0x00FF;
    $p       = pack('S', $testint);
    return $testint === current(unpack('v', $p));
  }

  public static function correctEndianness($binary)
  {
    // Reverse byte order for little-endian machines
    if( self::machineIsLittleEndian() )
    { return strrev($binary); }
    return $binary;
  }

  ///  \brief Take binary string with length % 4 = 0 as input
  ///  and return array of floats. Expectation is that this
  ///  is used for vectors, but any count is ok. 
  public static function unpackFloatVector( $bin )
  {
    if( ( strlen( $bin ) % 4 ) !== 0 )
    { return null; }

    $vector = str_split( $bin, 4 );
    foreach( $vector as $key => $value )
    { $vector[$key] = self::unpackFloat( $value ); }
    return $vector;
  }

  ///  \brief Take binary string with length 3 as input
  ///  and return array of char.
  public static function unpackCharArray( $bin )
  {
    if( strlen( $bin ) < 1 )
    { return null; }

    $Values = str_split( $bin );
    foreach( $Values as $key => $value )
    { $Values[$key] = self::unpackChar( $value ); }
    return $Values;
  }

  /// \brief Unpack a "row" or 16bits, assuming it as four LONG INT values
  /// \return array of int
  public static function unpackFourLong( $FileHandle )
  {
    $bin = fread( $FileHandle, 16 );
    $output = str_split( $bin, 4 );
    foreach( $output as $key => $value )
    {
      $output[$key] = current( unpack( 'N', $value ) );
    }
    return $output;
  }

  public static function extractLong( $FileHandle )
  {
    $bin = fread( $FileHandle, 4 );
    return self::unpackLong( $bin );
  }

  public static function extractFloat( $FileHandle )
  {
    $bin = fread( $FileHandle, 4 );
    return self::unpackFloat( $bin );
  }

  public static function extractChar( $FileHandle )
  {
    $bin = fread( $FileHandle, 1 );
    return self::unpackChar( $bin );
  }

  public static function unpackFloat( $bin )
  {
    if( strlen($bin) !== 4 )
    { return null; }
    $bin = Helper::correctEndianness( $bin );
    return current( unpack( 'f', $bin ) );
  }

  public static function unpackLong( $bin )
  {
    if( strlen($bin) !== 4 )
    { return null; }
    // 'N' is 32 bit big-endian long so no endian check necessary
    return current( unpack( 'N', $bin ) );
  }

  public static function unpackChar( $bin )
  {
    if( strlen($bin) !== 1 )
    { return null; }
    return current( unpack( 'C', $bin ) );
  }

}
