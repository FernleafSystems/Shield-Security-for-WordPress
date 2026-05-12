<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\LegacyPathCompatibilityPlan;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\LegacyPathDuplicator;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempPathJoinTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class LegacyPathDuplicatorTest extends TestCase {

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

	public function testCreateDuplicatesRemovesStaleCompatibilityOutputWhenNoPlanIsActive() :void {
		$this->fs->dumpFile( $this->tempPath( 'src/lib/stale.txt' ), 'stale' );

		$messages = [];
		$duplicator = $this->createDuplicator(
			LegacyPathCompatibilityPlan::current(),
			function ( string $message ) use ( &$messages ) :void {
				$messages[] = $message;
			}
		);

		$duplicator->createDuplicates( $this->tempDir );

		$this->assertDirectoryDoesNotExist( $this->tempPath( 'src/lib' ) );
		$this->assertTrue( \count( \array_filter(
			$messages,
			static fn( string $message ) :bool => \strpos( $message, 'No active legacy path duplicates configured' ) !== false
		) ) > 0 );
	}

	public function testCreateDuplicatesBuildsConfiguredCompatibilityOutputsAndRemovesStaleFiles() :void {
		$overrideRoot = $this->seedCompatibilitySourceStructure();
		$this->fs->dumpFile( $this->tempPath( 'src/lib/stale.txt' ), 'stale' );

		$duplicator = $this->createDuplicator( $this->createActivePlan(), null, $overrideRoot );
		$duplicator->createDuplicates( $this->tempDir );

		$this->assertDirectoryExists( $this->tempPath( 'src/lib/src/Controller/Config' ) );
		$this->assertFileExists( $this->tempPath( 'src/lib/src/Controller/Config/Options.php' ) );
		$this->assertFileExists( $this->tempPath( 'src/lib/src/LegacyProbe/CompatTarget.php' ) );
		$this->assertFileExists( $this->tempPath( 'src/lib/src/Legacy/CompatOverride.php' ) );
		$this->assertDirectoryExists( $this->tempPath( 'src/lib/vendor_prefixed/composer' ) );
		$this->assertFileExists( $this->tempPath( 'src/lib/vendor_prefixed/autoload.php' ) );
		$this->assertDirectoryExists( $this->tempPath( 'src/lib/vendor/fernleafsystems/wordpress-services/src' ) );
		$this->assertFileExists( $this->tempPath( 'src/lib/vendor/autoload.php' ) );
		$this->assertFileDoesNotExist( $this->tempPath( 'src/lib/stale.txt' ) );

		$this->assertStringContainsString(
			'legacy override',
			(string)\file_get_contents( $this->tempPath( 'src/lib/src/Legacy/CompatOverride.php' ) )
		);
	}

	public function testCreateDuplicatesFailsWhenConfiguredSourceFileIsMissing() :void {
		$overrideRoot = $this->seedCompatibilitySourceStructure();
		$this->fs->remove( $this->tempPath( 'src/NewLocation/CompatTarget.php' ) );

		$duplicator = $this->createDuplicator( $this->createActivePlan(), null, $overrideRoot );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Required legacy source file missing' );
		$duplicator->createDuplicates( $this->tempDir );
	}

	public function testCreateDuplicatesFailsWhenConfiguredOverrideIsMissing() :void {
		$this->seedCompatibilitySourceStructure();

		$duplicator = $this->createDuplicator( $this->createActivePlan(), null, $this->tempPath( 'missing-overrides' ) );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Required legacy override missing' );
		$duplicator->createDuplicates( $this->tempDir );
	}

	public function testCreateDuplicatesLogsProgressForActivePlan() :void {
		$overrideRoot = $this->seedCompatibilitySourceStructure();
		$messages = [];
		$duplicator = $this->createDuplicator(
			$this->createActivePlan(),
			function ( string $message ) use ( &$messages ) :void {
				$messages[] = $message;
			},
			$overrideRoot
		);

		$duplicator->createDuplicates( $this->tempDir );

		$this->assertTrue( \count( $messages ) > 0 );
		$this->assertTrue( \count( \array_filter(
			$messages,
			static fn( string $message ) :bool => \strpos( $message, 'Created legacy path duplicates' ) !== false
		) ) > 0 );
	}

	private function createDuplicator(
		LegacyPathCompatibilityPlan $plan,
		?callable $logger = null,
		?string $overridesRootDir = null
	) :LegacyPathDuplicator {
		return new LegacyPathDuplicator( $plan, $logger ?? static function () :void {}, $overridesRootDir );
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

	private function seedCompatibilitySourceStructure() :string {
		$this->fs->dumpFile(
			$this->tempPath( 'src/Controller/Config/Options.php' ),
			'<?php declare( strict_types=1 ); class Options {}'
		);
		$this->fs->dumpFile(
			$this->tempPath( 'src/NewLocation/CompatTarget.php' ),
			'<?php declare( strict_types=1 ); namespace FernleafSystems\\Wordpress\\Plugin\\Shield\\LegacyProbe; class CompatTarget { public function describe() :string { return "legacy-probe-ready"; } }'
		);
		$this->fs->dumpFile(
			$this->tempPath( 'vendor_prefixed/composer/Loader.php' ),
			'<?php declare( strict_types=1 ); class Loader {}'
		);
		$this->fs->dumpFile(
			$this->tempPath( 'vendor_prefixed/autoload.php' ),
			'<?php declare( strict_types=1 ); return true;'
		);
		$this->fs->dumpFile(
			$this->tempPath( 'vendor/fernleafsystems/wordpress-services/src/ServicesStub.php' ),
			'<?php declare( strict_types=1 ); class ServicesStub {}'
		);
		$this->fs->dumpFile(
			$this->tempPath( 'vendor/autoload.php' ),
			'<?php declare( strict_types=1 ); return true;'
		);

		$overrideRoot = $this->tempPath( 'legacy-overrides' );
		$this->fs->dumpFile(
			Path::join( $overrideRoot, 'CompatOverride.php' ),
			'<?php declare( strict_types=1 ); echo "legacy override";'
		);

		return $overrideRoot;
	}
}
