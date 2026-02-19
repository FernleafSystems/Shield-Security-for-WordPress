<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanAnalysisOrchestrator;
use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanOutcome;
use PHPUnit\Framework\TestCase;

class PackagedPhpStanAnalysisOrchestratorTest extends TestCase {

	private string $projectRoot;
	/**
	 * @var string[]
	 */
	private array $tempDirs = [];

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
	}

	protected function tearDown() :void {
		foreach ( $this->tempDirs as $dir ) {
			$this->removeDir( $dir );
		}
		$this->tempDirs = [];
		parent::tearDown();
	}

	public function testRunCommandReturnsCleanSuccessForZeroExit() :void {
		$outcome = ( new PackagedPhpStanAnalysisOrchestrator() )->runCommand(
			[ \PHP_BINARY, '-r', 'echo "ok"; exit(0);' ],
			$this->projectRoot,
			static function () :void {
			}
		);

		$this->assertSame( PackagedPhpStanOutcome::STATUS_CLEAN_SUCCESS, $outcome->getStatus() );
		$this->assertSame( 0, $outcome->toExitCode() );
	}

	public function testRunCommandReturnsFindingsSuccessForFileErrorsOnly() :void {
		$outcome = ( new PackagedPhpStanAnalysisOrchestrator() )->runCommand(
			[
				\PHP_BINARY,
				'-r',
				'echo "noise\n{\"totals\":{\"errors\":0,\"file_errors\":2}}\n"; exit(1);',
			],
			$this->projectRoot,
			static function () :void {
			}
		);

		$this->assertSame( PackagedPhpStanOutcome::STATUS_FINDINGS_SUCCESS, $outcome->getStatus() );
		$this->assertSame( 0, $outcome->toExitCode() );
	}

	public function testRunCommandReturnsHardFailureWhenTotalsErrorsExist() :void {
		$outcome = ( new PackagedPhpStanAnalysisOrchestrator() )->runCommand(
			[
				\PHP_BINARY,
				'-r',
				'echo "{\"totals\":{\"errors\":1,\"file_errors\":0}}"; exit(1);',
			],
			$this->projectRoot,
			static function () :void {
			}
		);

		$this->assertSame( PackagedPhpStanOutcome::STATUS_NON_REPORTABLE_FAILURE, $outcome->getStatus() );
		$this->assertSame( 1, $outcome->toExitCode() );
	}

	public function testRunCommandReturnsParseFailureForUnparseableOutput() :void {
		$outcome = ( new PackagedPhpStanAnalysisOrchestrator() )->runCommand(
			[
				\PHP_BINARY,
				'-r',
				'echo "not json"; exit(1);',
			],
			$this->projectRoot,
			static function () :void {
			}
		);

		$this->assertSame( PackagedPhpStanOutcome::STATUS_PARSE_FAILURE, $outcome->getStatus() );
		$this->assertSame( 1, $outcome->toExitCode() );
	}

	public function testRunCommandReturnsHardFailureForNonZeroWithZeroTotals() :void {
		$outcome = ( new PackagedPhpStanAnalysisOrchestrator() )->runCommand(
			[
				\PHP_BINARY,
				'-r',
				'echo "{\"totals\":{\"errors\":0,\"file_errors\":0}}"; exit(1);',
			],
			$this->projectRoot,
			static function () :void {
			}
		);

		$this->assertSame( PackagedPhpStanOutcome::STATUS_NON_REPORTABLE_FAILURE, $outcome->getStatus() );
		$this->assertSame( 1, $outcome->toExitCode() );
	}

	public function testBuildPackageContainerPathNormalizesRelativePath() :void {
		$path = ( new PackagedPhpStanAnalysisOrchestrator() )->buildPackageContainerPath( 'tmp\\shield-package-local/' );
		$this->assertSame( '/app/tmp/shield-package-local', $path );
	}

	public function testAssertPreflightThrowsWhenConfigMissing() :void {
		$orchestrator = new PackagedPhpStanAnalysisOrchestrator();
		$projectRoot = $this->createTempDir();
		$packageDir = $this->createTempDir();
		\mkdir( $projectRoot.'/tests/stubs', 0777, true );
		\mkdir( $packageDir.'/vendor', 0777, true );
		\mkdir( $packageDir.'/vendor_prefixed', 0777, true );
		\file_put_contents( $projectRoot.'/tests/stubs/phpstan-package-bootstrap.php', '<?php' );
		\file_put_contents( $packageDir.'/vendor/autoload.php', '<?php' );
		\file_put_contents( $packageDir.'/vendor_prefixed/autoload.php', '<?php' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'ERROR: Missing phpstan.package.neon.dist at project root' );
		$orchestrator->assertPreflight( $projectRoot, $packageDir );
	}

	public function testAssertPreflightThrowsWhenPackageVendorAutoloadMissing() :void {
		$orchestrator = new PackagedPhpStanAnalysisOrchestrator();
		$projectRoot = $this->createTempDir();
		$packageDir = $this->createTempDir();
		\mkdir( $projectRoot.'/tests/stubs', 0777, true );
		\mkdir( $packageDir.'/vendor_prefixed', 0777, true );
		\file_put_contents( $projectRoot.'/phpstan.package.neon.dist', 'includes: []' );
		\file_put_contents( $projectRoot.'/tests/stubs/phpstan-package-bootstrap.php', '<?php' );
		\file_put_contents( $packageDir.'/vendor_prefixed/autoload.php', '<?php' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'ERROR: Packaged vendor autoload not found:' );
		$orchestrator->assertPreflight( $projectRoot, $packageDir );
	}

	public function testAssertPreflightPassesForValidLayout() :void {
		$orchestrator = new PackagedPhpStanAnalysisOrchestrator();
		$projectRoot = $this->createTempDir();
		$packageDir = $this->createTempDir();
		\mkdir( $projectRoot.'/tests/stubs', 0777, true );
		\mkdir( $packageDir.'/vendor', 0777, true );
		\mkdir( $packageDir.'/vendor_prefixed', 0777, true );
		\file_put_contents( $projectRoot.'/phpstan.package.neon.dist', 'includes: []' );
		\file_put_contents( $projectRoot.'/tests/stubs/phpstan-package-bootstrap.php', '<?php' );
		\file_put_contents( $packageDir.'/vendor/autoload.php', '<?php' );
		\file_put_contents( $packageDir.'/vendor_prefixed/autoload.php', '<?php' );

		$orchestrator->assertPreflight( $projectRoot, $packageDir );
		$this->assertTrue( true );
	}

	private function createTempDir() :string {
		$path = \sys_get_temp_dir().'/shield-phpstan-orchestrator-'.\bin2hex( \random_bytes( 6 ) );
		\mkdir( $path, 0777, true );
		$this->tempDirs[] = $path;
		return $path;
	}

	private function removeDir( string $dir ) :void {
		if ( !\is_dir( $dir ) ) {
			return;
		}

		$items = \scandir( $dir );
		if ( !\is_array( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}
			$path = $dir.'/'.$item;
			if ( \is_dir( $path ) ) {
				$this->removeDir( $path );
			}
			else {
				@\unlink( $path );
			}
		}
		@\rmdir( $dir );
	}
}
