<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\LegacyPathCompatibilityPlan;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PackageVerifier;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempPathJoinTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class PackageVerifierTest extends TestCase {

	use TempPathJoinTrait;

	private string $tempDir;

	private Filesystem $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->fs = new Filesystem();
		$this->tempDir = Path::join( \sys_get_temp_dir(), 'shield-test-'.\uniqid() );
		$this->fs->mkdir( $this->tempDir );
	}

	protected function tearDown() :void {
		if ( \is_dir( $this->tempDir ) ) {
			$this->fs->remove( $this->tempDir );
		}
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
