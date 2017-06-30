<?php
require_once "Helper.php";

$scripts = array(
	'ExtractDecorationMeshInstances',
	'RebuildDecorationMeshInstances',
	'PlotPositions',
	'ExtractHulls',
);
$message = "Pick a script to run";

$selectedscripts = Helper::cliPrompt( $message, $scripts, "q" );

if( count( $selectedscripts ) !== 1 )
{ exit( "Select only one script\n" ); }

$classfile = $selectedscripts[0] . ".php";
$class = $selectedscripts[0];
require_once ( $classfile );
$action = $class::invokeViaWizard();
$action->run();
