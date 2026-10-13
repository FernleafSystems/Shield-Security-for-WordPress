<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * The PHP and WordPress facts the installer decides on, captured once so the installer has no direct
 * dependency on globals.
 */
final readonly class Environment {

	/**
	 * @param string $currentPrepend auto_prepend_file as this request sees it
	 */
	public function __construct(
		public string $sapi,
		public string $userIniFilename,
		public int $userIniCacheTtl,
		public string $currentPrepend,
		public string $serverSoftware,
		public bool $isMultisite,
		public bool $isDirectFilesystem,
		public bool $isWebRequest,
		public int $now
	) {
	}

	public function mechanism() :Mechanism {
		return Mechanism::detect( $this->sapi, $this->userIniFilename, $this->serverSoftware );
	}
}
