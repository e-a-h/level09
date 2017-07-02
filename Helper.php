<?php
require_once "InvocationOptions.php";

class HexifyFloats
{
	public static function invokeViaWizard()
	{
		return new self();
	}

	public function run()
	{
		print "Paste in a string of floats. I will output as Hex-formated binary. Floats should be comma-separated string. Spaces are optional.\n> ";
		$line = fgets( STDIN );
		$output = Helper::floatArrayToHex( array( 'f' => $line ) );

		print "\nHere you go:\n\n";
		print implode( '', $output );
		print "\n\n";
	}
}

/// \brief Helper class for reverse-engineering binary data
class Helper
{
  const HelpMultiLevel = 'Multi-Level';
  const HelpSingleLevel = 'Single Level';
  const HelpMel = 'Maya/MEL Export';
  const HelpDbIn = 'Db Insert';
  const HelpDbOut = 'Db Extract';
  const HelpStandardMode = 'Standard';
  const HelpDryRun = 'Dry Run (No disk/db modification)';

  public static $LevelList = array(
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

	public static function helpMe( $options ) : InvocationOptions
	{
    if( in_array( self::HelpMultiLevel, $options ) &&
        in_array( self::HelpSingleLevel, $options ) )
    { exit( 'Script misconfigured: Single and multi-level options are mutually exclusive' ); }

	  $HelpString = "";
	  $longoptions = array( 'help' );
	  $shortoptions = "h";
	  $SingleLevel = false;

    foreach( $options as $option )
    {
      switch( $option )
      {
        case self::HelpMultiLevel :
          $longoptions[] = 'level:';
          $shortoptions .= 'l:';
          $HelpString .= <<<EOT
-l [LevelName], --level [LevelName]
   Level: Specify level name or comma-separated list of level
   names. E.g. `-l Canyon,Bryan,Graveyard` or repeate the flag
   to operate on many levels, e.g. `--level Bryan --level Chris`
   If this flag is omitted, all levels will be processed.

EOT;
          break;
        case self::HelpSingleLevel :
          $SingleLevel = true;
          $longoptions[] = 'Level:';
          $shortoptions .= 'l:';
          $HelpString .= <<<EOT
-l [LevelName], --level [LevelName]
   Level [required]: Specify level name e.g. `-l Canyon` or `--level Barrens`
   Exactly one level is required for this script.

EOT;
          break;
        case self::HelpMel :
          $longoptions[] = 'MelExport';
          $shortoptions .= 'm';
          $HelpString .= <<<EOT
-m, --MelExport
   MEL (maya) export mode: Exports MEL script for creating
   locators with corresponding mesh names.

EOT;
          break;
        case self::HelpDbIn :
          $longoptions[] = 'DbIn';
          $shortoptions .= 'd';
          $HelpString .= <<<EOT
-d, --DbIn
   Database insert mode: Imports data to respective db tables.

EOT;
          break;
        case self::HelpDbOut :
          $longoptions[] = 'DbOut';
          $shortoptions .= 'x';
          $HelpString .= <<<EOT
-x, --DbOut
   Database extract mode: Exports data from respective db tables.

EOT;
          break;
        case self::HelpDryRun :
          $longoptions[] = 'DryRun';
          $shortoptions .= 'n';
          $HelpString .= <<<EOT
-n, --DryRun
   Dry Run mode: Do not modify database or disk files.

EOT;
          break;
      }
	  }

		$flags = getopt( $shortoptions, $longoptions );
		if( empty( $flags ) )
		{ return self::scriptWizard( $options ); }

		if( isset( $flags['h'] ) || isset( $flags['help'] ) )
		{
			print "Usage:\n";
			print $HelpString;
			print "-h, --help\n   Show this message.\n";
			exit();
		}

    $output = new InvocationOptions();
		$Level = array();

		if( ! empty( $flags['l'] ) )
		{
			$Level[] = ( is_array( $flags['l'] ) ) ? join( ',', $flags['l'] ) : $flags['l'];
		}
		if( ! empty( $flags['level'] ) )
		{
			$Level[] = ( is_array( $flags['level'] ) ) ? join( ',', $flags['level'] ) : $flags['level'];
		}
		if( ! empty( $Level ) )
		{
			$output->setLevels( self::filterLevels( $Level, $SingleLevel ) );
		}

		$output->setMelExport( isset( $flags['m'] ) || isset( $flags['MelExport'] ) );
		$output->setDbIn( isset( $flags['d'] ) || isset( $flags['DbIn'] ) );
		$output->setDbOut( isset( $flags['x'] ) || isset( $flags['DbOut'] ) );
		$output->setDryRun( isset( $flags['n'] ) || isset( $flags['DryRun'] ) );

		return $output;
	}

	public static function scriptWizard( $options ) : InvocationOptions
	{
		$output = new InvocationOptions();
		$modes = array( 's' => self::HelpStandardMode );

		// Build wizard from script's available options
		foreach( $options as $option )
		{
			switch( $option )
			{
				case self::HelpMultiLevel :
					$message = "Select a level (use commas to specify multiple):";
					$choices = self::$LevelList;
					$AllLevels = "All Levels";
					$choices[ 'a' ] = $AllLevels;
					$output->setLevels( self::cliPrompt( $message, $choices, $AllLevels ) );

					// All Levels chosen. overwrite level options with complete list
					if( in_array( $AllLevels, $output->getLevels() ) )
					{ $output->setLevels( self::$LevelList ); }

					$output->setLevels( self::filterLevels( $output->getLevels() ) );
					break;
				case self::HelpSingleLevel :
					$message = "Select a level:\n";
					$choices = self::$LevelList;
					$Levels = self::cliPrompt( $message, $choices );
					$output->setLevels( self::filterLevels( $Levels, true ) );
					break;
				case self::HelpMel :
					$modes['m'] = $option;
					break;
				case self::HelpDbIn :
					$modes['d'] = $option;
					break;
				case self::HelpDbOut :
					$modes['x'] = $option;
					break;
				case self::HelpDryRun :
					$modes['n'] = $option;
					break;
			}
		}

		if( ! empty( $modes ) )
		{
			$message = "Specify Output Mode:";
			$selectedmodes = self::cliPrompt( $message, $modes, "n" );
			foreach( $selectedmodes as $mode )
			{
				switch( $mode )
				{
					case self::HelpDryRun:
						$output->setDryRun( true );
						break;
					case self::HelpMel:
						$output->setMelExport( true );
						break;
					case self::HelpDbIn:
						$output->setDbIn( true );
						break;
					case self::HelpDbOut:
						$output->setDbOut( true );
						break;
					case self::HelpStandardMode:
						$output->setStandardMode( true );
						break;
				}
			}
		}

		return $output;
	}

  public static function cliPrompt( string $message = "", array $choices, string $default = null ):array
  {
    $selection = array();
    if( empty( $choices ) )
    { exit( "cliPrompt choices misconfigured.\n" ); }

    // Default to the first item in choices
    if( $default === null )
    { $default = reset( $choices ); }

    $choices['q'] = 'Quit';

    // Show message and options
    foreach( $choices as $index => $option )
    { $message .= "\n\t[$index]\t$option"; }
    print "$message\n";

    // show prompt line with options
    $choiceindex = array_keys( $choices );
    $choicestr = join( ", ", $choiceindex );
    // regex uses word boundaries to avoid matching "1" to any 2-digit number starting with 1
    $choicereg = '/\bq\b|\b' . join( '\b|\b', $choiceindex ) . '\b/i';

    while( empty( $line ) )
    {
      print "choose [ $choicestr ] (enter to select $default): ";
      // split on commas
      $line = fgets( STDIN );
      foreach( explode( ',', $line ) as $input )
      {
        // strip spaces
        $input = trim( $input );
        if( empty( $input ) && ( $input !== "0" ) )
        { continue; }

        $match = array();
        // match options
        if( ! preg_match( $choicereg, $input, $match ) )
        { continue; }

        $selection[] = $choices[ $match[0] ];
      }

      // Apply default selection
      if( empty( $selection ) )
      { $selection[] = $choices[$default]; }

      // always quit on "q"
      if( in_array( 'Quit', $selection ) )
      { exit( "Thanks for playing, come again soon!\n" ); }

      // confirm
      print "\n[[ You selected " . join( ', ', $selection ) . " ]]\n\n\n";
    }
    return $selection;
  }

	public static function filterLevels( $LevelInput, $SingleLevel = false ):array
  {
		$LevelWhitelist = self::$LevelList;
    $SelectedLevels = array();
    $FilteredLevels = array();

		if( ! empty( $LevelInput ) )
		{
			if( is_string( $LevelInput ) )
			{ $LevelInput = array( $LevelInput ); }

			// separate all the named levels into a single array
			foreach( $LevelInput as $LevelString )
			{ $SelectedLevels = array_merge( $SelectedLevels, explode( ',', $LevelString ) ); }

			// remove levels that don't match the level whitelist
      foreach( $LevelWhitelist as $LevelName )
      {
        if( in_array( strtolower( $LevelName ), array_map( 'strtolower', $SelectedLevels ) ) )
        {
          $FilteredLevels[] = $LevelName;
        }
			}
		}

		if( $SingleLevel && count( $FilteredLevels ) !== 1 )
		{ exit( "Specify one level!\n" ); }

		if( empty( $FilteredLevels ) )
		{ exit( "Specify a level!\n" ); }

		return $FilteredLevels;
	}

	public static function correctEndianness( $binary )
	{
		// Reverse byte order for little-endian machines
		if( self::machineIsLittleEndian() )
		{ return strrev($binary); }
		return $binary;
	}

	public static function floatArrayToHex( array $options = null )
	{
		if( empty( $options ) )
		{ $options = getopt( 'f:d:a' ); }

		$delimiter = " ";
		if( ! empty( $options['d'] ) )
		{ $delimiter = $options['d']; }

		if( ! empty( $options['f'] ) )
		{
			$floatstring = preg_replace( '/[\n\r]/', '', $options['f'] );
			$floats = explode( $delimiter, $floatstring );
		}
		else
		{
			print "Takes an input of a string of floating point numbers. Numbers are converted\n" .
				"to big-endian binary and concatenated. Output is hex representation of the\n" .
				"resulting binary string. Crazy, right?\n";
			print "Usage:\n";
			print '-f [required string of floats, e.g. "1.0 2.3 4 0"]';
			print "\n";
			print '-d [optional delimiter text, e.g. ","]';
			print "\n";
			exit();
		}

		$output = array();
		foreach( $floats as $val )
		{
			$output[] = bin2hex( self::float2bin( $val ) );
		}

		// -a is for debug
		if( ! empty( $options['a'] ) )
		{
			print "\n";
			Helper::plog( $output );
			print "\n";
			print "\n";
			return $output;
		}

		return $output;
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
	 * Pack a float into binary
	 *
	 * @param $float
	 *
	 * @return string
	 */
	public static function float2bin( string $float ) : string
	{
		$bin = pack( 'f', $float );
		return Helper::correctEndianness( $bin );
	}

	/**
	 * Translate ascii into hex
	 *
	 * @param $string
	 * @param int $lines
	 *
	 * @return string
	 */
	public static function str2bin( string $string, int $lines = 4 ) : string
	{
		$hex = $output = '';

		foreach( str_split( $string ) as $character )
		{
			// convert each character's ordinal value to hex
			$hex .= dechex( ord( $character ) );
		}

		// pad resulting hex with zeroes to 32 characters per line
		$hex = str_pad( $hex, $lines * 32, '0' );

		// hmmm... this looks like
		// split the code block into an array of characters,
		// split the array into arrays of 32 characters,
		// join the line array back to a string
		// convert the hex string to binary
		// repeat for each line and concatenate.

		// WHY???

		// specifically: why not hex2bin the whole block and save the effort?
		foreach( array_chunk( str_split( $hex ), 32 ) as $line )
		{
			$output .= hex2bin( implode( $line ) );
		}

		// test
//		$test_str = hex2bin( $hex );
//		$isSame = strcasecmp( $test_str, $output );
//
//		var_dump( $isSame );
//		exit;
		// end test

		return $output;
	}

	/**
	 * Return a 0-filled string. A chunk is 4 hex characters
	 *
	 * @param $chunks
	 *
	 * @return string
	 */
	public static function pad( int $chunks ) : string
	{
		return str_repeat( "0", $chunks * 4 );
	}

	/**
	 * Take a string and convert into a float tuple
	 * Pass 'false' into the second parameter to avoid including a fourth value
	 *
	 * @param $line
	 * @param bool $includeLast
	 *
	 * @return string
	 */
	public static function floatTuple2Bin( string $line, bool $includeLast = true ) : string
	{
		// TODO: enforce line length?
		$tuple = explode( ' ', $line );
		$output = self::float2bin( array_shift( $tuple ) );
		$output .= self::float2bin( array_shift( $tuple ) );
		$output .= self::float2bin( array_shift( $tuple ) );

		if( $includeLast )
		{
			$output .= empty( $tuple ) ?
				hex2bin( self::pad( 2 ) ) :
				self::float2bin( array_shift( $tuple ) );
		}

		return $output;
	}

	/**
	 * @param $directory (String) Directory name
	 * @param $verbose (Bool) Print debug information
   * @return string
	 */
	public static function validateDirectory( string $directory, bool $verbose = false )
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

	/**
	 * This skips all hidden files & directories
	 *
	 * @param string $directory
	 *
	 * @return array
	 */
	public static function getDirectoryContents( string $directory ) : array
	{
		$contents = scandir( $directory );

		// ignore hidden files & directories
		while( strpos( $contents[0], '.' ) === 0 )
		{ array_shift( $contents ); }

		return $contents;
	}

}
