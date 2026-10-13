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

	/**
	 * Which PHP files the early pass covers, see EarlyPass::coversScript().
	 */
	public array $scope;

	public function __construct(
		string $stub,
		string $stubUrl,
		string $htaccess,
		?string $userIni,
		string $bootstrap,
		string $ruleset,
		array $scope
	) {
		$this->stub = $stub;
		$this->stubUrl = $stubUrl;
		$this->htaccess = $htaccess;
		$this->userIni = $userIni;
		$this->bootstrap = $bootstrap;
		$this->ruleset = $ruleset;
		$this->scope = $scope;
	}

	/**
	 * Every configuration file Shield's block may be in, whatever the current mechanism.
	 * @return string[]
	 */
	public function configFiles() :array {
		return $this->userIni === null ? [ $this->htaccess ] : [ $this->htaccess, $this->userIni ];
	}
}
