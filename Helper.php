<?php

/// \brief Helper class for reverse-engineering binary data
class Helper
{
	const HelpMultiLevel = 0;
	const HelpSingleLevel = 1;
	const HelpMel = 2;

	public static function plog( $str, $exit = false )
	{
		if( ! is_string( $str ) )
		{ $str = print_r( $str, 1); }
		print "$str\r\n";
		if( $exit )
		{ exit(); }
	}

	public static function machineIsLittleEndian()
	{
		$testint = 0x00FF;
		$p       = pack('S', $testint);
		return $testint === current(unpack('v', $p));
	}

	public static function helpMe( $options )
	{
		// Array of options presented by the calling script
		$MultiLevel = in_array( self::HelpMultiLevel, $options );
		$SingleLevel = in_array( self::HelpSingleLevel, $options );
		$Mel = in_array( self::HelpMel, $options );
		
		$options = getopt( 'h' );
		if( isset( $options['h'] ) )
		{
			print "Usage:\n";
			if( $MultiLevel )
			{
echo <<< EOF
-l Level: Specify level name or comma-separated list of level
   names. E.g. `-l Canyon,Bryan,Graveyard` or repeate the flag
   to operate on many levels, e.g. `-level Bryan -level Chris`
   If this flag is omitted, all levels will be processed.

EOF;
			}
			
			if( ! $MultiLevel && $SingleLevel )
			{
echo <<< EOF
-l Level [required]: Specify level name e.g. `-l Canyon`
   Exactly one level is required for this script.

EOF;
			}

			if( $Mel )
			{
echo <<< EOF
-m MEL (maya) export mode [optional]: Exports MEL script for creating
   locators with corresponding mesh names

EOF;
			}

			print "-h Show this message.\n";
			exit();
		}
	}

	public static function filterLevels( $SingleLevel = false )
	{
		$levels = array(
			'Barrens',
			'Bryan',
			'Canyon',
			'Cave',
			'Chris',
			'Credits',
			'Desert',
			'Graveyard',
			'Matt',
			'Mountain',
			'Ruins',
			'Summit'
		);

		$options = getopt( "l:" );

		if( ! empty( $options ) && ! empty( $options['l'] ) )
		{
			$paramlevels = array();
			$params = $options['l'];

			if( is_string( $params ) )
			{ $params = array( $params ); }

			foreach( $params as $levelstring )
			{ $paramlevels = array_merge( $paramlevels, explode( ',', $levelstring ) ); }
			foreach( $paramlevels as $key => $level )
			{
				if( ! in_array( $level, $levels, true ) )
				{ unset( $paramlevels[$key] ); }
			}
			$levels = array_values( $paramlevels ); // re-index
		}

		if( $SingleLevel )
		{
			if( count( $levels ) !== 1 )
			{ exit( "Specifcy one level!\n" ); }
			return $levels[0];
		}

		if( empty( $levels ) )
		{ exit( "Specifcy a level!\n" ); }
		return $levels;
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
	function validateDirectory( $directory, $verbose = false )
	{
		if( $path = realpath( $directory ) )
		{
			if( $verbose )
			{ echo "Using directory: $path\n"; }
			return $path;
		}
		else
		{
			$path = realpath( "./" ) . '/' . $directory;
			if( mkdir( $path, 0777, true ) )
			{
				if( $verbose )
				{ echo "Created directory: $path\n"; }
				return realpath( $path );
			}
		}
		return false;
	}

}
