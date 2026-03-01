<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\CommandRunner;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\SafeDirectoryRemover;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\StraussBinaryProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class StraussBinaryProviderTest extends TestCase {

	use InvokesNonPublicMethods;
	use TempDirLifecycleTrait;

	private string $projectRoot;

	private StraussBinaryProvider $provider;

	private string $tempDir;

	protected function setUp() :void {
		parent::setUp();

		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
		$logger = static function ( string $message ) :void {
		};

		$this->provider = new StraussBinaryProvider(
			'0.26.5',
			null,
			new CommandRunner( $this->projectRoot, $logger ),
			new SafeDirectoryRemover( $this->projectRoot ),
			$logger
		);
		$this->tempDir = $this->createTrackedTempDir( 'shield-strauss-provider-test-' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testMissingOpenSslCaFileThrowsDetailedException() :void {
		$missingPath = Path::join( $this->tempDir, 'missing-cacert.pem' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'openssl.cafile' );
		$this->expectExceptionMessage( 'HOW TO FIX' );

		$this->invokePathValidation( 'openssl.cafile', $missingPath, false );
	}

	public function testMissingOpenSslCaDirectoryThrowsDetailedException() :void {
		$missingPath = Path::join( $this->tempDir, 'missing-capath' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'openssl.capath' );
		$this->expectExceptionMessage( 'HOW TO FIX' );

		$this->invokePathValidation( 'openssl.capath', $missingPath, true );
	}

	public function testReadableOpenSslCaFilePassesValidation() :void {
		$caFilePath = Path::join( $this->tempDir, 'cacert.pem' );
		$this->assertNotFalse( \file_put_contents( $caFilePath, 'dummy-ca-file' ) );

		$this->invokePathValidation( 'openssl.cafile', $caFilePath, false );
		$this->addToAssertionCount( 1 );
	}

	public function testReadableOpenSslCaDirectoryPassesValidation() :void {
		$caDirPath = Path::join( $this->tempDir, 'ca-certs' );
		$this->assertTrue( \mkdir( $caDirPath, 0777, true ) );

		$this->invokePathValidation( 'openssl.capath', $caDirPath, true );
		$this->addToAssertionCount( 1 );
	}

	public function testEmptyConfiguredPathSkipsValidation() :void {
		$this->invokePathValidation( 'openssl.cafile', '', false );
		$this->invokePathValidation( 'openssl.capath', '', true );
		$this->addToAssertionCount( 1 );
	}

	public function testRunPrefixingPassesWhenRequiredPackagesExist() :void {
		$this->assertTrue( \mkdir( Path::join( $this->tempDir, 'vendor_prefixed' ), 0777, true ) );
		$this->createRequiredPackage( 'monolog/monolog' );

		$provider = $this->createProvider( $this->createNoopStraussScript() );
		$provider->runPrefixing( $this->tempDir, [ 'monolog/monolog' ] );

		$this->assertDirectoryExists( Path::join( $this->tempDir, 'vendor_prefixed', 'monolog', 'monolog' ) );
	}

	public function testRunPrefixingFailsWhenVendorPrefixedDirectoryMissingAfterStrauss() :void {
		$provider = $this->createProvider( $this->createNoopStraussScript() );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'vendor_prefixed directory was not created' );
		$provider->runPrefixing( $this->tempDir, [ 'monolog/monolog' ] );
	}

	public function testRunPrefixingFailsWhenRequiredPackageMissing() :void {
		$this->assertTrue( \mkdir( Path::join( $this->tempDir, 'vendor_prefixed' ), 0777, true ) );
		$this->createRequiredPackage( 'monolog/monolog' );

		$provider = $this->createProvider( $this->createNoopStraussScript() );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'twig/twig' );
		$provider->runPrefixing( $this->tempDir, [ 'monolog/monolog', 'twig/twig' ] );
	}

	public function testRunPrefixingFailsWhenRequiredPackageDirectoryIsEmpty() :void {
		$this->assertTrue( \mkdir( Path::join( $this->tempDir, 'vendor_prefixed' ), 0777, true ) );
		$this->createRequiredPackage( 'twig/twig', false );

		$provider = $this->createProvider( $this->createNoopStraussScript() );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'twig/twig' );
		$provider->runPrefixing( $this->tempDir, [ 'twig/twig' ] );
	}

	public function testRunPrefixingSkipsRequiredPackageAssertionWhenRequiredListEmpty() :void {
		$this->assertTrue( \mkdir( Path::join( $this->tempDir, 'vendor_prefixed' ), 0777, true ) );

		$provider = $this->createProvider( $this->createNoopStraussScript() );
		$provider->runPrefixing( $this->tempDir, [] );

		$this->assertDirectoryExists( Path::join( $this->tempDir, 'vendor_prefixed' ) );
	}

	private function invokePathValidation( string $settingName, string $configuredPath, bool $expectsDirectory ) :void {
		$this->invokeNonPublicMethod(
			$this->provider,
			'assertConfiguredOpenSslPathValid',
			[
				$settingName,
				$configuredPath,
				$expectsDirectory,
				'https://github.com/BrianHenryIE/strauss/releases/download/0.26.5/strauss.phar',
				Path::join( $this->tempDir, 'strauss.phar' ),
			]
		);
	}

	private function createNoopStraussScript() :string {
		$script = Path::join( $this->tempDir, 'fake-strauss.php' );
		$this->assertNotFalse( \file_put_contents( $script, '<?php exit( 0 );' ) );
		return $script;
	}

	private function createProvider( string $straussScriptPath, ?callable $logger = null ) :StraussBinaryProvider {
		$logger = $logger ?? static function ( string $message ) :void {
		};
		$commandRunner = new CommandRunner( $this->projectRoot, $logger );
		$directoryRemover = new SafeDirectoryRemover( $this->projectRoot );

		return new class( $straussScriptPath, $commandRunner, $directoryRemover, $logger ) extends StraussBinaryProvider {

			private string $straussScriptPath;

			public function __construct(
				string $straussScriptPath,
				CommandRunner $commandRunner,
				SafeDirectoryRemover $directoryRemover,
				callable $logger
			) {
				$this->straussScriptPath = $straussScriptPath;
				parent::__construct( '0.19.4', null, $commandRunner, $directoryRemover, $logger );
			}

			public function provide( string $targetDir ) :string {
				return $this->straussScriptPath;
			}
		};
	}

	private function createRequiredPackage( string $package, bool $withFile = true ) :void {
		$packagePath = Path::join( $this->tempDir, 'vendor_prefixed', $package );
		$this->assertTrue( \mkdir( $packagePath, 0777, true ) );
		if ( $withFile ) {
			$this->assertNotFalse( \file_put_contents( Path::join( $packagePath, 'Class.php' ), '<?php' ) );
		}
	}
}
