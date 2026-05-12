<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceSetupCacheCoordinator;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class SourceSetupCacheCoordinatorTest extends TestCase {

	use TempDirLifecycleTrait;

	private SourceSetupCacheCoordinator $coordinator;

	protected function setUp() :void {
		parent::setUp();
		$this->coordinator = new SourceSetupCacheCoordinator();
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testFirstRuntimeEvaluationRequiresFullSetup() :void {
		$rootDir = $this->createFixtureRoot();
		$decision = $this->coordinator->evaluateRuntimeSetup( $rootDir, '8.2' );

		$this->assertTrue( $decision[ 'needs_composer_install' ] );
		$this->assertTrue( $decision[ 'needs_build_config' ] );
		$this->assertTrue( $decision[ 'needs_npm_install' ] );
		$this->assertTrue( $decision[ 'needs_npm_build' ] );
		$this->assertStringStartsWith( 'shield-source-node-modules-', $decision[ 'node_modules_volume' ] );
	}

	public function testRuntimeEvaluationSkipsSetupAfterStatePersisted() :void {
		$rootDir = $this->createFixtureRoot();
		$firstDecision = $this->coordinator->evaluateRuntimeSetup( $rootDir, '8.2' );
		$this->coordinator->persistRuntimeSetupState( $rootDir, $firstDecision[ 'fingerprints' ] );

		$secondDecision = $this->coordinator->evaluateRuntimeSetup( $rootDir, '8.2' );
		$this->assertFalse( $secondDecision[ 'needs_composer_install' ] );
		$this->assertFalse( $secondDecision[ 'needs_build_config' ] );
		$this->assertFalse( $secondDecision[ 'needs_npm_install' ] );
		$this->assertFalse( $secondDecision[ 'needs_npm_build' ] );
	}

	public function testAssetSourceChangeTriggersBuildWithoutDependencyInstall() :void {
		$rootDir = $this->createFixtureRoot();
		$firstDecision = $this->coordinator->evaluateRuntimeSetup( $rootDir, '8.2' );
		$this->coordinator->persistRuntimeSetupState( $rootDir, $firstDecision[ 'fingerprints' ] );

		$this->writeFixtureFile( $rootDir, 'assets/js/plugin-main.js', 'console.log("changed");' );
		$decision = $this->coordinator->evaluateRuntimeSetup( $rootDir, '8.2' );

		$this->assertFalse( $decision[ 'needs_npm_install' ] );
		$this->assertTrue( $decision[ 'needs_npm_build' ] );
	}

	public function testNodeDependencyChangeTriggersInstallAndBuild() :void {
		$rootDir = $this->createFixtureRoot();
		$firstDecision = $this->coordinator->evaluateRuntimeSetup( $rootDir, '8.2' );
		$this->coordinator->persistRuntimeSetupState( $rootDir, $firstDecision[ 'fingerprints' ] );

		$this->writeFixtureFile( $rootDir, 'package-lock.json', '{"lockfileVersion":3,"changed":true}' );
		$decision = $this->coordinator->evaluateRuntimeSetup( $rootDir, '8.2' );

		$this->assertTrue( $decision[ 'needs_npm_install' ] );
		$this->assertTrue( $decision[ 'needs_npm_build' ] );
	}

	public function testMissingRecordedDistArtifactTriggersBuild() :void {
		$rootDir = $this->createFixtureRoot();
		$firstDecision = $this->coordinator->evaluateRuntimeSetup( $rootDir, '8.2' );
		$this->coordinator->persistRuntimeSetupState( $rootDir, $firstDecision[ 'fingerprints' ] );

		@unlink( Path::join( $rootDir, 'assets', 'dist', 'shield-main.bundle.js' ) );
		$decision = $this->coordinator->evaluateRuntimeSetup( $rootDir, '8.2' );

		$this->assertFalse( $decision[ 'needs_npm_install' ] );
		$this->assertTrue( $decision[ 'needs_npm_build' ] );
	}

	public function testCorruptedStateFallsBackToCacheMiss() :void {
		$rootDir = $this->createFixtureRoot();
		$firstDecision = $this->coordinator->evaluateRuntimeSetup( $rootDir, '8.2' );
		$this->coordinator->persistRuntimeSetupState( $rootDir, $firstDecision[ 'fingerprints' ] );

		$this->writeFixtureFile( $rootDir, 'tmp/.source-setup-cache/state.v1.json', '{ invalid json' );
		$decision = $this->coordinator->evaluateRuntimeSetup( $rootDir, '8.2' );

		$this->assertTrue( $decision[ 'needs_composer_install' ] );
		$this->assertTrue( $decision[ 'needs_build_config' ] );
		$this->assertTrue( $decision[ 'needs_npm_install' ] );
		$this->assertTrue( $decision[ 'needs_npm_build' ] );
	}

	public function testAnalyzeEvaluationTracksBuildConfigFingerprint() :void {
		$rootDir = $this->createFixtureRoot();
		$first = $this->coordinator->evaluateAnalyzeSetup( $rootDir );
		$this->assertTrue( $first[ 'needs_build_config' ] );

		$this->coordinator->persistBuildConfigState( $rootDir, $first[ 'fingerprint' ] );
		$second = $this->coordinator->evaluateAnalyzeSetup( $rootDir );
		$this->assertFalse( $second[ 'needs_build_config' ] );

		$this->writeFixtureFile( $rootDir, 'plugin-spec/01_properties.json', '{"version":"1.1.0"}' );
		$third = $this->coordinator->evaluateAnalyzeSetup( $rootDir );
		$this->assertTrue( $third[ 'needs_build_config' ] );
	}

	private function createFixtureRoot() :string {
		$rootDir = $this->createTrackedTempDir( 'shield-setup-cache-' );

		$this->writeFixtureFile( $rootDir, 'composer.json', '{"name":"fixture"}' );
		$this->writeFixtureFile( $rootDir, 'composer.lock', '{"packages":[]}' );
		$this->writeFixtureFile( $rootDir, 'vendor/autoload.php', '<?php' );

		$this->writeFixtureFile( $rootDir, 'package.json', '{"name":"fixture"}' );
		$this->writeFixtureFile( $rootDir, 'package-lock.json', '{"lockfileVersion":3}' );

		$this->writeFixtureFile( $rootDir, 'plugin.json', '{}' );
		$this->writeFixtureFile( $rootDir, 'bin/build-config.php', '<?php' );
		$this->writeFixtureFile( $rootDir, 'plugin-spec/01_properties.json', '{"version":"1.0.0"}' );

		$this->writeFixtureFile( $rootDir, 'assets/js/plugin-main.js', 'console.log("fixture");' );
		$this->writeFixtureFile( $rootDir, 'assets/css/plugin-main.scss', '.x { color: red; }' );
		$this->writeFixtureFile( $rootDir, 'assets/images/logo.svg', '<svg></svg>' );
		$this->writeFixtureFile( $rootDir, 'webpack.config.js', 'module.exports = {};' );
		$this->writeFixtureFile( $rootDir, 'postcss.config.js', 'module.exports = {};' );
		$this->writeFixtureFile( $rootDir, 'assets/dist/shield-main.bundle.js', 'bundle' );

		return $rootDir;
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
