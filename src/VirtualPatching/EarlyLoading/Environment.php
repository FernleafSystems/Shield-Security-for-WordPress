<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

/**
 * The PHP and WordPress facts the installer decides on, captured once so the installer has no direct
 * dependency on globals.
 */
final class Environment {

	public string $sapi;

	public string $userIniFilename;

	public int $userIniCacheTtl;

	/**
	 * auto_prepend_file as this request sees it.
	 */
	public string $currentPrepend;

	public string $serverSoftware;

	public bool $isMultisite;

	public bool $isDirectFilesystem;

	public bool $isWebRequest;

	public int $now;

	public function __construct(
		string $sapi,
		string $userIniFilename,
		int $userIniCacheTtl,
		string $currentPrepend,
		string $serverSoftware,
		bool $isMultisite,
		bool $isDirectFilesystem,
		bool $isWebRequest,
		int $now
	) {
		$this->sapi = $sapi;
		$this->userIniFilename = $userIniFilename;
		$this->userIniCacheTtl = $userIniCacheTtl;
		$this->currentPrepend = $currentPrepend;
		$this->serverSoftware = $serverSoftware;
		$this->isMultisite = $isMultisite;
		$this->isDirectFilesystem = $isDirectFilesystem;
		$this->isWebRequest = $isWebRequest;
		$this->now = $now;
	}

	public function mechanism() :Mechanism {
		return Mechanism::detect( $this->sapi, $this->userIniFilename, $this->serverSoftware );
	}
}
