<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class SafeReplacementTest extends TestCase {

	use TempDirLifecycleTrait;

	private Filesystem $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->fs = new Filesystem();
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testJsonDecodeThrowsJsonExceptionOnInvalidJson() :void {
		$this->expectException( \Safe\Exceptions\JsonException::class );
		\Safe\json_decode( '{invalid json' );
	}

	public function testUnlinkThrowsFilesystemExceptionForMissingFile() :void {
		$missingFile = $this->createTrackedTempPath( 'shield-safe-missing-', '.tmp' );
		$this->assertFileDoesNotExist( $missingFile );

		$scriptPath = $this->createTrackedTempPath( 'shield-safe-unlink-', '.php' );
		$pluginRoot = $this->getSourceRoot();
		$script = <<<'PHP'
<?php declare(strict_types=1);
require %s;
try {
    \Safe\unlink(%s);
    echo 'missing';
}
catch (\Safe\Exceptions\FilesystemException $e) {
    echo 'ok';
}
PHP;

		$this->fs->dumpFile(
			$scriptPath,
			\sprintf(
				$script,
				\var_export( Path::join( $pluginRoot, 'vendor', 'autoload.php' ), true ),
				\var_export( $missingFile, true )
			)
		);

		$process = new Process( [ PHP_BINARY, $scriptPath ], $pluginRoot );
		$process->run();

		$this->assertSame( 0, $process->getExitCode(), $process->getErrorOutput() );
		$this->assertSame( 'ok', \trim( $process->getOutput() ) );
	}

	public function testDateTimeImmutableSetTimestampReturnsSafeSubclass() :void {
		$dateTime = ( new \Safe\DateTimeImmutable( 'now' ) )->setTimestamp( 123 );

		$this->assertInstanceOf( \Safe\DateTimeImmutable::class, $dateTime );
		$this->assertSame( 123, $dateTime->getTimestamp() );
	}

	public function testSafeBootstrapCanBeRequiredMultipleTimes() :void {
		$scriptPath = $this->createTrackedTempPath( 'shield-safe-bootstrap-', '.php' );
		$pluginRoot = $this->getSourceRoot();
		$vendorAutoload = Path::join( $pluginRoot, 'vendor', 'autoload.php' );
		$safeBootstrap = Path::join( $pluginRoot, 'vendor', 'thecodingmachine', 'safe', 'src', 'functions.php' );

		$script = <<<'PHP'
<?php declare(strict_types=1);
require %s;
require %s;
require %s;
echo \function_exists('Safe\\json_decode') ? 'ok' : 'missing';
PHP;

		$this->fs->dumpFile(
			$scriptPath,
			\sprintf(
				$script,
				\var_export( $vendorAutoload, true ),
				\var_export( $safeBootstrap, true ),
				\var_export( $safeBootstrap, true )
			)
		);

		$process = new Process( [ PHP_BINARY, $scriptPath ], $pluginRoot );
		$process->run();

		$this->assertSame( 0, $process->getExitCode(), $process->getErrorOutput() );
		$this->assertSame( 'ok', \trim( $process->getOutput() ) );
	}

	private function getSourceRoot() :string {
		return \dirname( __DIR__, 2 );
	}
}
