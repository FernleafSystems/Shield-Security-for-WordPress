<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

final class Paths {

	public string $stub;

	public string $stubUrl;

	public string $htaccess;

	/**
	 * Null when PHP does not read per-directory INI files.
	 */
	public ?string $userIni;

	public string $bootstrap;

	public string $ruleset;

	public function __construct( string $stub, string $stubUrl, string $htaccess, ?string $userIni, string $bootstrap, string $ruleset ) {
		$this->stub = $stub;
		$this->stubUrl = $stubUrl;
		$this->htaccess = $htaccess;
		$this->userIni = $userIni;
		$this->bootstrap = $bootstrap;
		$this->ruleset = $ruleset;
	}

	/**
	 * Every configuration file Shield's block may be in, whatever the current mechanism.
	 * @return array<string,MarkedBlock>
	 */
	public function configFiles() :array {
		$files = [ $this->htaccess => MarkedBlock::htaccess() ];
		if ( $this->userIni !== null ) {
			$files[ $this->userIni ] = MarkedBlock::userIni();
		}
		return $files;
	}
}
