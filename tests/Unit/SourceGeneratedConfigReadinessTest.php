<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceGeneratedConfigReadiness;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingSourceAnalyzeSetupCacheCoordinator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class SourceGeneratedConfigReadinessTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = $this->createTrackedTempDir( 'shield-generated-config-readiness-' );
		$this->seedMetadataFiles( $this->projectRoot, '21.99.3', '21.99.3', '21.99.3', '202604.1701', '202604.1701' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testCacheHitSkipsBuildConfigAndChecksMetadata() :void {
		$processRunner = new RecordingProcessRunner();
		$setupCoordinator = new RecordingSourceAnalyzeSetupCacheCoordinator( false, 'fingerprint-hit' );
		$readiness = new SourceGeneratedConfigReadiness( $processRunner, $setupCoordinator );

		$readiness->ensureReady( $this->projectRoot );

		$this->assertCount( 0, $processRunner->calls );
		$this->assertCount( 0, $setupCoordinator->persistCalls );
	}

	public function testCacheMissRunsBuildConfigAndPersistsState() :void {
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$setupCoordinator = new RecordingSourceAnalyzeSetupCacheCoordinator( true, 'fingerprint-miss' );
		$readiness = new SourceGeneratedConfigReadiness( $processRunner, $setupCoordinator );

		$readiness->ensureReady( $this->projectRoot, static function () :void {} );

		$this->assertCount( 1, $processRunner->calls );
		$this->assertSame( [ \PHP_BINARY, './bin/build-config.php' ], $processRunner->calls[ 0 ][ 'command' ] );
		$this->assertTrue( $processRunner->calls[ 0 ][ 'has_output_callback' ] );
		$this->assertCount( 1, $setupCoordinator->persistCalls );
		$this->assertSame( 'fingerprint-miss', $setupCoordinator->persistCalls[ 0 ][ 'fingerprint' ] );
	}

	public function testBuildConfigFailureFailsClearlyAndDoesNotPersistState() :void {
		$processRunner = new RecordingProcessRunner( [
			[
				'exit_code' => 1,
				'stderr'    => 'build failed',
			],
		] );
		$setupCoordinator = new RecordingSourceAnalyzeSetupCacheCoordinator( true, 'fingerprint-fail' );
		$readiness = new SourceGeneratedConfigReadiness( $processRunner, $setupCoordinator );

		$this->expectExceptionMessage( 'Failed to regenerate plugin.json for source tooling. build failed' );

		try {
			$readiness->ensureReady( $this->projectRoot );
		}
		finally {
			$this->assertCount( 0, $setupCoordinator->persistCalls );
		}
	}

	public function testMetadataMismatchFailsClearlyAfterCacheHit() :void {
		$this->seedMetadataFiles( $this->projectRoot, '21.99.3', '21.99.3', '21.99.4', '202604.1701', '202604.1801' );
		$readiness = new SourceGeneratedConfigReadiness(
			new RecordingProcessRunner(),
			new RecordingSourceAnalyzeSetupCacheCoordinator( false, 'fingerprint-mismatch' )
		);

		$this->expectExceptionMessage( 'Generated plugin.json is out of sync with plugin-spec/01_properties.json' );

		$readiness->ensureReady( $this->projectRoot );
	}

	private function seedMetadataFiles(
		string $rootDir,
		string $headerVersion,
		string $sourceVersion,
		string $configVersion,
		string $sourceBuild,
		string $configBuild
	) :void {
		$this->writeFixtureFile(
			$rootDir,
			'plugin-spec/01_properties.json',
			\json_encode( [
				'version' => $sourceVersion,
				'build' => $sourceBuild,
			], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ) ?: '{}'
		);
		$this->writeFixtureFile(
			$rootDir,
			'plugin.json',
			\json_encode( [
				'properties' => [
					'version' => $configVersion,
					'build' => $configBuild,
				],
			], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES ) ?: '{}'
		);
		$this->writeFixtureFile(
			$rootDir,
			'icwp-wpsf.php',
			"<?php\n/*\n * Version: {$headerVersion}\n */\n"
		);
	}

	private function writeFixtureFile( string $rootDir, string $relativePath, string $contents ) :void {
		$filePath = Path::join( $rootDir, $relativePath );
		$parent = \dirname( $filePath );
		if ( !\is_dir( $parent ) ) {
			\mkdir( $parent, 0777, true );
		}
		\file_put_contents( $filePath, $contents );
	}
}
