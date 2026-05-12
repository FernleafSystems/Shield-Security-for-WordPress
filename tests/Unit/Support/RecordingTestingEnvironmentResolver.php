<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\TestingEnvironmentResolver;

class RecordingTestingEnvironmentResolver extends TestingEnvironmentResolver {

	public bool $assertDockerReadyCalled = false;

	private string $phpVersion;

	/** @var array{string,string} */
	private array $wordpressVersions;

	/**
	 * @param array{string,string} $wordpressVersions
	 */
	public function __construct( string $phpVersion = '8.2', array $wordpressVersions = [ '6.9', '6.8.3' ] ) {
		parent::__construct();
		$this->phpVersion = $phpVersion;
		$this->wordpressVersions = $wordpressVersions;
	}

	public function assertDockerReady( string $rootDir ) :void {
		$this->assertDockerReadyCalled = true;
	}

	public function resolvePhpVersion( string $rootDir ) :string {
		return $this->phpVersion;
	}

	/**
	 * @return array{string,string}
	 */
	public function detectWordpressVersions( string $rootDir ) :array {
		return $this->wordpressVersions;
	}

	public function writeDockerEnvFile( string $dockerEnvPath, array $lines ) :void {
	}
}
