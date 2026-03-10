<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceSetupCacheCoordinator;

class RecordingSourceAnalyzeSetupCacheCoordinator extends SourceSetupCacheCoordinator {

	private bool $needsBuildConfig;

	private string $fingerprint;

	/** @var array<int,array{root_dir:string,fingerprint:string}> */
	public array $persistCalls = [];

	public int $clearCalls = 0;

	public function __construct( bool $needsBuildConfig, string $fingerprint ) {
		$this->needsBuildConfig = $needsBuildConfig;
		$this->fingerprint = $fingerprint;
	}

	public function clearState( string $rootDir ) :void {
		$this->clearCalls++;
	}

	public function evaluateAnalyzeSetup( string $rootDir, bool $refreshSetup = false ) :array {
		return [
			'needs_build_config' => $this->needsBuildConfig,
			'fingerprint' => $this->fingerprint,
		];
	}

	public function persistBuildConfigState( string $rootDir, string $buildConfigFingerprint ) :void {
		$this->persistCalls[] = [
			'root_dir' => $rootDir,
			'fingerprint' => $buildConfigFingerprint,
		];
	}
}
