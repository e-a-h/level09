<?php

/**
 * Class InvocationOptions
 */
class InvocationOptions
{
	private $levels = array();
	private $DbIn = false;
	private $DbOut = false;
	private $DryRun = false;
	private $MelExport = false;
	private $StandardMode = false;

	/**
	 * InvocationOptions constructor.
	 *
	 * @param array $Levels
	 * @param bool $StandardMode
	 * @param bool $DbIn
	 * @param bool $DbOut
	 * @param bool $DryRun
	 * @param bool $MelExport
	 */
	public function __construct(
		array $Levels = array(),
		bool  $StandardMode = false,
		bool  $DbIn = false,
		bool  $DbOut = false,
		bool  $DryRun = false,
		bool  $MelExport = false )
	{
		$this->setLevels( $Levels );
		$this->setDbIn( $DbIn );
		$this->setDbOut( $DbOut );
		$this->setDryRun( $DryRun );
		$this->setMelExport( $MelExport );
		$this->setStandardMode( $StandardMode );
	}

	public function isStandardMode(): bool
	{ return $this->StandardMode; }

	public function setStandardMode( bool $StandardMode )
	{ $this->StandardMode = $StandardMode; }

	public function isMelExport(): bool
	{ return $this->MelExport; }

	public function setMelExport( bool $MelExport )
	{ $this->MelExport = $MelExport; }

	public function getLevels() : array
	{ return $this->levels; }

	public function setLevels( array $levels )
	{ $this->levels = $levels; }

	public function isDbIn() : bool
	{ return $this->DbIn; }

	public function setDbIn( bool $DbIn )
	{ $this->DbIn = $DbIn; }

	public function isDbOut() : bool
	{ return $this->DbOut; }

	public function setDbOut( bool $DbOut )
	{ $this->DbOut = $DbOut; }

	public function isDryRun() : bool
	{ return $this->DryRun; }

	public function setDryRun( bool $DryRun )
	{ $this->DryRun = $DryRun; }

}