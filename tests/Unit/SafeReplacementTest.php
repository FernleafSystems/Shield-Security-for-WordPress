<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class SafeReplacementTest extends TestCase {

	private Filesystem $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->fs = new Filesystem();
	}

	public function testJsonDecodeThrowsJsonExceptionOnInvalidJson() :void {
		$this->expectException( \Safe\Exceptions\JsonException::class );
		\Safe\json_decode( '{invalid json' );
	}

	public function testUnlinkThrowsFilesystemExceptionForMissingFile() :void {
		$missingFile = Path::join( \sys_get_temp_dir(), 'shield-safe-missing-'.\uniqid().'.tmp' );
		$this->assertFileDoesNotExist( $missingFile );

		$scriptPath = Path::join( \sys_get_temp_dir(), 'shield-safe-unlink-'.\uniqid().'.php' );
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

		try {
			$process = new Process( [ PHP_BINARY, $scriptPath ], $pluginRoot );
			$process->run();

			$this->assertSame( 0, $process->getExitCode(), $process->getErrorOutput() );
			$this->assertSame( 'ok', \trim( $process->getOutput() ) );
		}
		finally {
			if ( \file_exists( $scriptPath ) ) {
				$this->fs->remove( $scriptPath );
			}
		}
	}

	public function testDateTimeImmutableSetTimestampReturnsSafeSubclass() :void {
		$dateTime = ( new \Safe\DateTimeImmutable( 'now' ) )->setTimestamp( 123 );

		$this->assertInstanceOf( \Safe\DateTimeImmutable::class, $dateTime );
		$this->assertSame( 123, $dateTime->getTimestamp() );
	}

	public function testSafeBootstrapCanBeRequiredMultipleTimes() :void {
		$scriptPath = Path::join( \sys_get_temp_dir(), 'shield-safe-bootstrap-'.\uniqid().'.php' );
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

		try {
			$process = new Process( [ PHP_BINARY, $scriptPath ], $pluginRoot );
			$process->run();

			$this->assertSame( 0, $process->getExitCode(), $process->getErrorOutput() );
			$this->assertSame( 'ok', \trim( $process->getOutput() ) );
		}
		finally {
			if ( \file_exists( $scriptPath ) ) {
				$this->fs->remove( $scriptPath );
			}
		}
	}

	private function getSourceRoot() :string {
		return \dirname( __DIR__, 2 );
	}
}
