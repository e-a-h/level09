<?php
require_once 'Helper.php';
$options = getopt( 'f:d:a' );

$delimiter = " ";
if( !empty( $options['d'] ) )
{
  $delimiter = $options['d'];
}

if( ! empty( $options['f'] ) )
{
  $floatstring = preg_replace( '/[\n\r]/', '', $options['f'] );
  $floats = explode( $delimiter, $floatstring );
}
else
{
  print "Takes an input of a string of floating point numbers. Numbers are converted\n".
        "to big-endian binary and concatenated. Output is hex representation of the\n".
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
  $val = pack( 'f', $val );
  $output[] = bin2hex(strrev($val));
}

if( !empty( $options['a'] ) )
{
  print "\n";
  Helper::plog($output);
  print "\n";
  print "\n";
  exit();
}

print "\n";
print implode( '', $output );
print "\n";
print "\n";
exit();

