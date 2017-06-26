<?php
require_once 'Scriptable.php';

class LevelProcessor implements Scriptable
{
	protected $level = '';
	protected $Invocation;

	public function __construct( InvocationOptions $Options )
	{
		$this->Invocation = $Options;
	}

	public static function invokeViaWizard() : Scriptable
	{
		$Options = Helper::helpMe( static::$ConfigOptions );
		return new static( $Options );
	}

	public function run()
  {
    $this->processLevels();
  }

	public function processLevels()
  {
		foreach( $this->Invocation->getLevels() as $Level )
		{ $this->handleLevel( $Level ); }
	}

	public function handleLevel( string $Level )
	{
		// Initiate for this level
		$this->level = $Level;
	}

}