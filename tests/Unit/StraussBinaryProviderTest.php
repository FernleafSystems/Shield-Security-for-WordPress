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

	private StraussBinaryProvider $provider;

	private string $tempDir;

	protected function setUp() :void {
		parent::setUp();

		$projectRoot = \dirname( \dirname( __DIR__ ) );
		$logger = static function ( string $message ) :void {
		};

		$this->provider = new StraussBinaryProvider(
			'0.26.5',
			null,
			new CommandRunner( $projectRoot, $logger ),
			new SafeDirectoryRemover( $projectRoot ),
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
}
