<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceAssetBuildReadiness;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use PHPUnit\Framework\TestCase;

class SourceAssetBuildReadinessTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testEnsureReadyRunsWebpackThroughRepoNodeTool() :void {
		$rootDir = $this->createTrackedTempDir( 'shield-asset-readiness-' );
		$processRunner = new RecordingProcessRunner( [ 0 ] );
		$output = [];

		( new SourceAssetBuildReadiness( $processRunner ) )->ensureReady(
			$rootDir,
			static function ( string $type, string $buffer ) use ( &$output ) :void {
				$output[] = [ $type, $buffer ];
			}
		);

		$this->assertSame(
			[
				\PHP_BINARY,
				'./bin/run-node-tool.php',
				'webpack',
				'--config',
				'webpack.config.js',
				'--mode',
				'production',
			],
			$processRunner->calls[ 0 ][ 'command' ]
		);
		$this->assertSame( $rootDir, $processRunner->calls[ 0 ][ 'working_dir' ] );
		$this->assertTrue( $processRunner->calls[ 0 ][ 'has_output_callback' ] );
	}

	public function testEnsureReadyThrowsWithProcessErrorOutput() :void {
		$rootDir = $this->createTrackedTempDir( 'shield-asset-readiness-fail-' );
		$processRunner = new RecordingProcessRunner( [
			[
				'exit_code' => 2,
				'stderr'    => 'webpack broke',
			],
		] );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to rebuild browser assets for browser tests. webpack broke' );

		( new SourceAssetBuildReadiness( $processRunner ) )->ensureReady( $rootDir );
	}
}
