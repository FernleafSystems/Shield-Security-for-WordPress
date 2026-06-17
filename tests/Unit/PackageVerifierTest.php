<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\LegacyPathCompatibilityPlan;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PackageVerifier;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempPathJoinTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class PackageVerifierTest extends TestCase {

	use TempPathJoinTrait;
	use TempDirLifecycleTrait;

	private string $tempDir;

	private Filesystem $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->fs = new Filesystem();
		$this->tempDir = $this->createTrackedTempDir( 'shield-package-verifier-' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testVerifyPassesWithEmptyActivePlanAndNoLegacyOutput() :void {
		$this->setupValidPackage();

		$this->expectNotToPerformAssertions();
		$this->createVerifier()->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenStaleLegacyOutputExistsWithoutActivePlan() :void {
		$this->setupValidPackage();
		$this->fs->dumpFile( $this->tempPath( 'src/lib/stale.txt' ), 'stale' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'src/lib legacy compatibility output' );
		$this->createVerifier()->verify( $this->tempDir );
	}

	public function testVerifyPassesWhenPlannedCompatibilityOutputsExist() :void {
		$plan = $this->createActivePlan();
		$this->setupValidPackage();
		$this->materializeCompatibilityOutputs( $plan );

		$this->expectNotToPerformAssertions();
		$this->createVerifier( $plan )->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenPlannedCompatibilityDirectoryIsMissing() :void {
		$plan = $this->createActivePlan();
		$this->setupValidPackage();
		$this->materializeCompatibilityOutputs( $plan );
		$this->fs->remove( $this->tempPath( 'src/lib/src/Controller/Config' ) );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'src/lib/src/Controller/Config directory' );
		$this->createVerifier( $plan )->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenPlannedCompatibilityFileIsMissing() :void {
		$plan = $this->createActivePlan();
		$this->setupValidPackage();
		$this->materializeCompatibilityOutputs( $plan );
		$this->fs->remove( $this->tempPath( 'src/lib/src/Legacy/CompatOverride.php' ) );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'src/lib/src/Legacy/CompatOverride.php file' );
		$this->createVerifier( $plan )->verify( $this->tempDir );
	}

	public function testVerifyChecksRequiredPrefixedPackages() :void {
		$this->setupValidPackage();
		$this->fs->mkdir( $this->tempPath( 'vendor_prefixed/monolog/monolog' ) );
		$this->fs->dumpFile( $this->tempPath( 'vendor_prefixed/monolog/monolog/Logger.php' ), '<?php' );

		$this->expectNotToPerformAssertions();
		$this->createVerifier()->verify( $this->tempDir, [ 'Monolog/Monolog' ] );
	}

	public function testVerifyFailsWhenTemplatesTwigDirectoryIsMissing() :void {
		$this->setupValidPackage();
		$this->fs->remove( $this->tempPath( 'templates/twig' ) );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'templates/twig directory' );
		$this->createVerifier()->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenStaticTwigReferenceIsMissing() :void {
		$this->setupValidPackage();
		$this->fs->dumpFile(
			$this->tempPath( 'templates/twig/wpadmin/plugin_pages/inner/import.twig' ),
			"{% include '/wpadmin_pages/insights/importexport/to_file.twig' %}\n"
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'to_file.twig' );
		$this->createVerifier()->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenMultiLineStaticTwigReferenceIsMissing() :void {
		$this->setupValidPackage();
		$this->fs->dumpFile(
			$this->tempPath( 'templates/twig/wpadmin/plugin_pages/inner/import.twig' ),
			<<<'TWIG'
{% include
	'/wpadmin_pages/insights/importexport/to_file.twig'
	with { export: export }
	only
%}
TWIG
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'to_file.twig' );
		$this->createVerifier()->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenStaticTwigImportReferenceIsMissing() :void {
		$this->setupValidPackage();
		$this->fs->dumpFile(
			$this->tempPath( 'templates/twig/wpadmin/components/page/shield_detail_row.twig' ),
			"{% import '/common/macros.twig' as icwp_macros %}\n"
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'common/macros.twig' );
		$this->createVerifier()->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenProcessorComponentReferenceIsMissingFromLoaderMap() :void {
		$this->setupValidPackage();
		$this->writeProcessor( <<<'PHP'
<?php
$components->license->execute();
$components->hidden_plugins->execute();
PHP );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'hidden_plugins' );
		$this->createVerifier()->verify( $this->tempDir );
	}

	public function testVerifyFailsWhenMappedComponentClassFileIsMissing() :void {
		$this->setupValidPackage();
		$this->writeComponentLoaderMap( [
			'hidden_plugins' => 'CompCons\HiddenPluginsCon',
			'license'        => 'LicenseHandler',
		] );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'HiddenPluginsCon.php' );
		$this->createVerifier()->verify( $this->tempDir );
	}

	private function createVerifier(
		?LegacyPathCompatibilityPlan $plan = null,
		?callable $logger = null
	) :PackageVerifier {
		return new PackageVerifier( $plan ?? LegacyPathCompatibilityPlan::current(), $logger ?? static function () :void {} );
	}

	private function createActivePlan() :LegacyPathCompatibilityPlan {
		return new LegacyPathCompatibilityPlan(
			[ 'Controller/Config' ],
			[ 'NewLocation/CompatTarget.php' => 'LegacyProbe/CompatTarget.php' ],
			[ 'CompatOverride.php' => 'Legacy/CompatOverride.php' ],
			[ 'composer' ],
			[ 'autoload.php' => 'autoload.php' ],
			[ 'fernleafsystems/wordpress-services/src' ],
			[ 'autoload.php' => 'autoload.php' ]
		);
	}

	private function setupValidPackage() :void {
		$this->fs->dumpFile( $this->tempPath( 'plugin.json' ), '{}' );
		$this->fs->dumpFile( $this->tempPath( 'icwp-wpsf.php' ), '<?php' );
		$this->fs->dumpFile( $this->tempPath( 'vendor/autoload.php' ), '<?php' );
		$this->fs->mkdir( $this->tempPath( 'vendor_prefixed' ) );
		$this->fs->mkdir( $this->tempPath( 'assets/dist' ) );
		$this->fs->mkdir( $this->tempPath( 'templates/twig' ) );
		$this->writeProcessor( <<<'PHP'
<?php
$components->license->execute();
PHP );
		$this->writeComponentLoaderMap( [ 'license' => 'LicenseHandler' ] );
		$this->writePhp( 'src/Modules/License/Lib/LicenseHandler.php', <<<'PHP'
<?php
namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib;

class LicenseHandler {}
PHP );
	}

	private function writeProcessor( string $content ) :void {
		$this->writePhp( 'src/Modules/Plugin/Processor.php', $content );
	}

	private function writePhp( string $relativePath, string $content ) :void {
		$this->fs->dumpFile( $this->tempPath( $relativePath ), $content );
	}

	/**
	 * @param array<int|string,string> $entries
	 */
	private function writeComponentLoaderMap( array $entries ) :void {
		$normalized = [];
		foreach ( $entries as $key => $class ) {
			if ( \is_int( $key ) ) {
				$key = $class;
				$class = \ucfirst( $class );
			}
			$normalized[ $key ] = $class;
		}

		$entries = \implode( "\n", \array_map(
			static fn( string $key, string $class ) :string => \sprintf( "\t\t\t'%s' => %s::class,", $key, $class ),
			\array_keys( $normalized ),
			$normalized
		) );
		$this->writePhp( 'src/Components/ComponentLoader.php', <<<PHP
<?php
namespace FernleafSystems\Wordpress\Plugin\Shield\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\LicenseHandler;

class ComponentLoader {
	private function getConsMap() :array {
		return [
{$entries}
		];
	}
}
PHP );
	}

	private function materializeCompatibilityOutputs( LegacyPathCompatibilityPlan $plan ) :void {
		foreach ( $plan->expectedDirectoryOutputs( $this->tempDir ) as $path ) {
			$this->fs->mkdir( $path );
		}

		foreach ( $plan->expectedFileOutputs( $this->tempDir ) as $path ) {
			$this->fs->dumpFile( $path, '<?php declare( strict_types=1 );' );
		}
	}
}
