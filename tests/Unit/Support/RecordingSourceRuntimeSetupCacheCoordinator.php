<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceSetupCacheCoordinator;

class RecordingSourceRuntimeSetupCacheCoordinator extends SourceSetupCacheCoordinator {

	/**
	 * @var array{
	 *   needs_composer_install:bool,
	 *   needs_build_config:bool,
	 *   needs_npm_install:bool,
	 *   needs_npm_build:bool,
	 *   node_modules_volume:string,
	 *   fingerprints:array{composer:string,build_config:string,node_deps:string,asset_inputs:string}
	 * }
	 */
	private array $decision;

	/** @var array<int,array{root_dir:string,fingerprints:array{composer:string,build_config:string,node_deps:string,asset_inputs:string}}> */
	public array $persistCalls = [];

	public int $clearCalls = 0;

	/**
	 * @param array{
	 *   needs_composer_install:bool,
	 *   needs_build_config:bool,
	 *   needs_npm_install:bool,
	 *   needs_npm_build:bool,
	 *   node_modules_volume:string,
	 *   fingerprints:array{composer:string,build_config:string,node_deps:string,asset_inputs:string}
	 * } $decision
	 */
	public function __construct( array $decision ) {
		$this->decision = $decision;
	}

	public function clearState( string $rootDir ) :void {
		$this->clearCalls++;
	}

	public function evaluateRuntimeSetup( string $rootDir, string $phpVersion, bool $refreshSetup = false ) :array {
		return $this->decision;
	}

	public function persistRuntimeSetupState( string $rootDir, array $fingerprints ) :void {
		$this->persistCalls[] = [
			'root_dir' => $rootDir,
			'fingerprints' => $fingerprints,
		];
	}

	public function getNodeModulesVolumeName( string $rootDir ) :string {
		return $this->decision[ 'node_modules_volume' ];
	}
}
