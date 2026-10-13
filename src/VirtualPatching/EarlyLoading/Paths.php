<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

final readonly class Paths {

	/**
	 * @param ?string $userIni null when PHP does not read per-directory INI files
	 * @param array   $scope   which PHP files the early pass covers, see EarlyPass::coversScript()
	 */
	public function __construct(
		public string $stub,
		public string $stubUrl,
		public string $htaccess,
		public ?string $userIni,
		public string $bootstrap,
		public string $ruleset,
		public array $scope
	) {
	}

	/**
	 * Every configuration file Shield's block may be in, whatever the current mechanism.
	 * @return string[]
	 */
	public function configFiles() :array {
		return $this->userIni === null ? [ $this->htaccess ] : [ $this->htaccess, $this->userIni ];
	}
}
