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

	public static function unpackStringOfFloats( $bin, $Separator = " " )
	{
		return join( $Separator, self::unpackFloatVector( $bin ) );
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

	/**
	 * Translate hex code into ascii
	 * @param $hex
	 * @return string
	 */
	public static function hex2str( $hex )
	{
		$str = '';

		//trim any empty 2-byte chunks off the right side
		while( preg_match( '/00$/', $hex ) )
		{ $hex = substr( $hex, 0, -2 ); }

		//get 2-byte chunks, encode decimal, then get ascii
		for( $i=0; $i < strlen( $hex ); $i += 2 )
		{ $str .= chr( hexdec( substr( $hex, $i, 2 ) ) ); }

		return $str;
	}

	/**
 * @param $directory (String) Directory name
 */
	function validateDirectory( $directory )
	{
		if( $path = realpath( $directory ) )
		{ return $path; }
		else
		{
			$path = realpath( "./" ) . '/' . $directory;
			if( mkdir( $path, 0777, true ) )
			{ return realpath( $path ); }
		}
		return false;
	}

}
