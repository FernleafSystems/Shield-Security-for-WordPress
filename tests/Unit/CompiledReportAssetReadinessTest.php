<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\CompiledReportAssetReadiness;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingSourceAssetBuildReadiness;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class CompiledReportAssetReadinessTest extends TestCase {

	use TempDirLifecycleTrait;

	/** @var array<string,string|false> */
	private array $originalEnvironment = [];

	protected function setUp() :void {
		parent::setUp();
		foreach ( $this->outerReceiptEnvironmentNames() as $name ) {
			$this->originalEnvironment[ $name ] = \getenv( $name );
			\putenv( $name );
		}
	}

	protected function tearDown() :void {
		foreach ( $this->originalEnvironment as $name => $value ) {
			\putenv( $value === false ? $name : $name.'='.$value );
		}
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testOrdinaryLocalModeAlwaysBuildsBeforeCachingSuccessfulRoot() :void {
		$rootDir = $this->createTrackedTempDir( 'shield-compiled-report-ready-' );
		$this->writeBundle( $rootDir, 'existing bundle' );
		$assetBuildReadiness = new RecordingSourceAssetBuildReadiness();
		$readiness = new CompiledReportAssetReadiness( $assetBuildReadiness );

		$readiness->ensureReady( $rootDir );
		$readiness->ensureReady( $rootDir.\DIRECTORY_SEPARATOR );

		$this->assertSame( [
			[
				'root_dir'            => Path::canonicalize( $rootDir ),
				'has_output_callback' => false,
				'failure_context'     => 'PHP integration tests that render compiled reports',
			],
		], $assetBuildReadiness->calls );
	}

	public function testBuildSuccessWithoutBundleFailsAndDoesNotMarkRootReady() :void {
		$rootDir = $this->createTrackedTempDir( 'shield-compiled-report-missing-' );
		$assetBuildReadiness = new RecordingSourceAssetBuildReadiness();
		$readiness = new CompiledReportAssetReadiness( $assetBuildReadiness );

		$firstFailure = $this->captureFailure( static function () use ( $readiness, $rootDir ) :void {
			$readiness->ensureReady( $rootDir );
		} );
		$this->assertStringContainsString( 'shield-reports.bundle.js', $firstFailure->getMessage() );

		$this->writeBundle( $rootDir, 'compiled bundle' );
		$readiness->ensureReady( $rootDir );

		$this->assertCount( 2, $assetBuildReadiness->calls );
	}

	public function testBuildFailurePropagatesAndDoesNotAcceptExistingBundle() :void {
		$rootDir = $this->createTrackedTempDir( 'shield-compiled-report-build-failure-' );
		$this->writeBundle( $rootDir, 'stale bundle' );
		$buildFailure = new \RuntimeException( 'webpack failed' );
		$events = [];
		$assetBuildReadiness = new RecordingSourceAssetBuildReadiness( $events, $buildFailure );
		$readiness = new CompiledReportAssetReadiness( $assetBuildReadiness );

		$this->assertSame( $buildFailure, $this->captureFailure( static function () use ( $readiness, $rootDir ) :void {
			$readiness->ensureReady( $rootDir );
		} ) );
		$this->assertSame( $buildFailure, $this->captureFailure( static function () use ( $readiness, $rootDir ) :void {
			$readiness->ensureReady( $rootDir );
		} ) );

		$this->assertCount( 2, $assetBuildReadiness->calls );
	}

	public function testSupportedSourceOuterReceiptSkipsBuildAndRequiresBundle() :void {
		\putenv( 'SHIELD_SKIP_INNER_SETUP=1' );
		\putenv( 'SHIELD_TEST_MODE=docker' );
		$rootDir = $this->createTrackedTempDir( 'shield-compiled-report-source-receipt-' );
		$this->writeBundle( $rootDir, 'compiled bundle' );
		$assetBuildReadiness = new RecordingSourceAssetBuildReadiness();

		( new CompiledReportAssetReadiness( $assetBuildReadiness ) )->ensureReady( $rootDir );

		$this->assertSame( [], $assetBuildReadiness->calls );
	}

	public function testSkipMarkerOutsideDockerDoesNotSuppressLocalBuild() :void {
		\putenv( 'SHIELD_SKIP_INNER_SETUP=1' );
		\putenv( 'SHIELD_TEST_MODE=source' );
		$rootDir = $this->createTrackedTempDir( 'shield-compiled-report-untrusted-receipt-' );
		$this->writeBundle( $rootDir, 'compiled bundle' );
		$assetBuildReadiness = new RecordingSourceAssetBuildReadiness();

		( new CompiledReportAssetReadiness( $assetBuildReadiness ) )->ensureReady( $rootDir );

		$this->assertCount( 1, $assetBuildReadiness->calls );
	}

	public function testPackageOuterReceiptSkipsBuildAndRequiresBundle() :void {
		\putenv( 'SHIELD_PACKAGE_PATH=/package' );
		$rootDir = $this->createTrackedTempDir( 'shield-compiled-report-package-receipt-' );
		$this->writeBundle( $rootDir, 'compiled bundle' );
		$assetBuildReadiness = new RecordingSourceAssetBuildReadiness();

		( new CompiledReportAssetReadiness( $assetBuildReadiness ) )->ensureReady( $rootDir );

		$this->assertSame( [], $assetBuildReadiness->calls );
	}

	/** @dataProvider provideOuterReceipts */
	public function testOuterReceiptsRejectEmptyBundle( string $receipt ) :void {
		if ( $receipt === 'source' ) {
			\putenv( 'SHIELD_SKIP_INNER_SETUP=1' );
			\putenv( 'SHIELD_TEST_MODE=docker' );
		}
		else {
			\putenv( 'SHIELD_PACKAGE_PATH=/package' );
		}
		$rootDir = $this->createTrackedTempDir( 'shield-compiled-report-empty-' );
		$this->writeBundle( $rootDir, '' );
		$assetBuildReadiness = new RecordingSourceAssetBuildReadiness();

		$failure = $this->captureFailure( static function () use ( $assetBuildReadiness, $rootDir ) :void {
			( new CompiledReportAssetReadiness( $assetBuildReadiness ) )->ensureReady( $rootDir );
		} );

		$this->assertStringContainsString( 'missing, unreadable, or empty', $failure->getMessage() );
		$this->assertSame( [], $assetBuildReadiness->calls );
	}

	public static function provideOuterReceipts() :array {
		return [
			'source'  => [ 'source' ],
			'package' => [ 'package' ],
		];
	}

	public function testDifferentRootCannotInheritReadiness() :void {
		$firstRoot = $this->createTrackedTempDir( 'shield-compiled-report-first-root-' );
		$secondRoot = $this->createTrackedTempDir( 'shield-compiled-report-second-root-' );
		$this->writeBundle( $firstRoot, 'first bundle' );
		$this->writeBundle( $secondRoot, 'second bundle' );
		$assetBuildReadiness = new RecordingSourceAssetBuildReadiness();
		$readiness = new CompiledReportAssetReadiness( $assetBuildReadiness );

		$readiness->ensureReady( $firstRoot );
		$readiness->ensureReady( $secondRoot );

		$this->assertSame(
			[ Path::canonicalize( $firstRoot ), Path::canonicalize( $secondRoot ) ],
			\array_column( $assetBuildReadiness->calls, 'root_dir' )
		);
	}

	private function writeBundle( string $rootDir, string $contents ) :void {
		$distDir = Path::join( $rootDir, 'assets', 'dist' );
		if ( !\is_dir( $distDir ) && !\mkdir( $distDir, 0777, true ) && !\is_dir( $distDir ) ) {
			throw new \RuntimeException( 'Failed to create test dist directory.' );
		}
		\file_put_contents( Path::join( $distDir, 'shield-reports.bundle.js' ), $contents );
	}

	/**
	 * @param callable():void $callback
	 */
	private function captureFailure( callable $callback ) :\RuntimeException {
		try {
			$callback();
		}
		catch ( \RuntimeException $e ) {
			return $e;
		}

		$this->fail( 'Expected a RuntimeException.' );
	}

	/** @return string[] */
	private function outerReceiptEnvironmentNames() :array {
		return [
			'SHIELD_SKIP_INNER_SETUP',
			'SHIELD_TEST_MODE',
			'SHIELD_PACKAGE_PATH',
		];
	}
}
