<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\CommandRunner;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\SafeDirectoryRemover;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\StraussBinaryProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class StraussBinaryProviderTest extends TestCase {

	private string $projectRoot;
	private string $tempDir;
	private Filesystem $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = dirname( dirname( __DIR__ ) );
		$this->tempDir = sys_get_temp_dir().'/shield-strauss-provider-test-'.uniqid();
		$this->fs = new Filesystem();
		$this->fs->mkdir( $this->tempDir );
	}

	protected function tearDown() :void {
		if ( is_dir( $this->tempDir ) ) {
			$this->fs->remove( $this->tempDir );
		}
		parent::tearDown();
	}

	private function createNoopStraussScript() :string {
		$script = $this->tempDir.'/fake-strauss.php';
		$this->fs->dumpFile( $script, '<?php exit( 0 );' );
		return $script;
	}

	private function createProvider( string $straussScriptPath, ?callable $logger = null ) :StraussBinaryProvider {
		$logger = $logger ?? function ( string $message ) :void {};
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
		$packagePath = $this->tempDir.'/vendor_prefixed/'.$package;
		$this->fs->mkdir( $packagePath );
		if ( $withFile ) {
			$this->fs->dumpFile( $packagePath.'/Class.php', '<?php' );
		}
	}

	public function testRunPrefixingPassesWhenRequiredPackagesExist() :void {
		$this->fs->mkdir( $this->tempDir.'/vendor_prefixed' );
		$this->createRequiredPackage( 'monolog/monolog' );

		$provider = $this->createProvider( $this->createNoopStraussScript() );
		$provider->runPrefixing( $this->tempDir, [ 'monolog/monolog' ] );

		$this->assertDirectoryExists( $this->tempDir.'/vendor_prefixed/monolog/monolog' );
	}

	public function testRunPrefixingFailsWhenVendorPrefixedDirectoryMissingAfterStrauss() :void {
		$provider = $this->createProvider( $this->createNoopStraussScript() );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'vendor_prefixed directory was not created' );
		$provider->runPrefixing( $this->tempDir, [ 'monolog/monolog' ] );
	}

	public function testRunPrefixingFailsWhenRequiredPackageMissing() :void {
		$this->fs->mkdir( $this->tempDir.'/vendor_prefixed' );
		$this->createRequiredPackage( 'monolog/monolog' );

		$provider = $this->createProvider( $this->createNoopStraussScript() );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'twig/twig' );
		$provider->runPrefixing( $this->tempDir, [ 'monolog/monolog', 'twig/twig' ] );
	}

	public function testRunPrefixingFailsWhenRequiredPackageDirectoryIsEmpty() :void {
		$this->fs->mkdir( $this->tempDir.'/vendor_prefixed' );
		$this->createRequiredPackage( 'twig/twig', false );

		$provider = $this->createProvider( $this->createNoopStraussScript() );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'twig/twig' );
		$provider->runPrefixing( $this->tempDir, [ 'twig/twig' ] );
	}

	public function testRunPrefixingSkipsRequiredPackageAssertionWhenRequiredListEmpty() :void {
		$this->fs->mkdir( $this->tempDir.'/vendor_prefixed' );

		$provider = $this->createProvider( $this->createNoopStraussScript() );
		$provider->runPrefixing( $this->tempDir, [] );

		$this->assertDirectoryExists( $this->tempDir.'/vendor_prefixed' );
	}
}
