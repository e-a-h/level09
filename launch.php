<?php
require_once "Helper.php";

$scripts = array(
	'ExtractDecorationMeshInstances',
	'RebuildDecorationMeshInstances',
	'PlotPositions',
	'ExtractHulls',
	'HexifyFloats'
);
$message = "Pick a script to run";
$selectedscripts = Helper::cliPrompt( $message, $scripts, "q" );

if( count( $selectedscripts ) !== 1 )
{
	exit( "Select only one script\n" );
}

$class = $selectedscripts[0];
if( ! class_exists( $class ) )
{
	$classfile = "$class.php";
	require_once( $classfile );
}

$action = $class::invokeViaWizard();
$action->run();
