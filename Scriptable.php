<?php
require_once "Helper.php";
require_once "InvocationOptions.php";

interface Scriptable
{
	public function __construct( InvocationOptions $Options );
	public static function invokeViaWizard() : self;
	public function processLevels();
	public function handleLevel( string $Level );
}
