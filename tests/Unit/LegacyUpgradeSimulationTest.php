<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\LegacyPathCompatibilityPlan;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\LegacyPathDuplicator;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempPathJoinTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

class LegacyUpgradeSimulationTest extends TestCase {

	use TempPathJoinTrait;

	private const PROBE_CLASS = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\LegacyProbe\\CompatTarget';

	private string $projectRoot;

	private string $tempDir;

	private Filesystem $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
		$this->fs = new Filesystem();
		$this->tempDir = Path::join( \sys_get_temp_dir(), 'shield-upgrade-sim-'.\uniqid() );
		$this->fs->mkdir( $this->tempDir );
		$this->seedMovedClassSource();
	}

	protected function tearDown() :void {
		if ( \is_dir( $this->tempDir ) ) {
			$this->fs->remove( $this->tempDir );
		}
		parent::tearDown();
	}

	public function testLegacyAutoloaderCannotResolveMovedClassBeforeDuplication() :void {
		$result = $this->runLegacyProbe( $this->tempDir, 'precheck' );

		$this->assertTrue( $result[ 'ok' ] ?? false, \json_encode( $result ) );
		$this->assertFalse( (bool)( $result[ 'checks' ][ 'class_found' ][ 'found' ] ?? true ) );
	}

	public function testLegacyAutoloaderResolvesMovedClassAfterDuplication() :void {
		$plan = new LegacyPathCompatibilityPlan(
			[],
			[ 'NewLocation/CompatTarget.php' => 'LegacyProbe/CompatTarget.php' ]
		);

		( new LegacyPathDuplicator( $plan, static function () :void {} ) )->createDuplicates( $this->tempDir );
		$result = $this->runLegacyProbe( $this->tempDir, 'load' );

		$this->assertTrue( $result[ 'ok' ] ?? false, \json_encode( $result ) );
		$this->assertSame( 'legacy-probe-ready', $result[ 'checks' ][ 'class_loaded' ][ 'details' ][ 'value' ] ?? '' );
		$this->assertStringContainsString(
			'/src/lib/src/LegacyProbe/CompatTarget.php',
			$this->normalisePath( (string)( $result[ 'checks' ][ 'class_loaded' ][ 'details' ][ 'file' ] ?? '' ) )
		);
	}

	private function seedMovedClassSource() :void {
		$this->fs->dumpFile(
			$this->tempPath( 'src/NewLocation/CompatTarget.php' ),
			<<<'PHP'
<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\LegacyProbe;

class CompatTarget {

	public function describe() :string {
		return 'legacy-probe-ready';
	}
}
PHP
		);
	}

	private function runLegacyProbe( string $pluginRoot, string $scenario ) :array {
		$probePath = Path::join( $this->projectRoot, 'tests/fixtures/legacy-upgrade/legacy_probe.php' );
		$process = new Process(
			[
				\PHP_BINARY,
				$probePath,
				'--plugin-root='.$this->normalisePath( $pluginRoot ),
				'--scenario='.$scenario,
				'--class-name='.self::PROBE_CLASS,
				'--method=describe',
			],
			$this->projectRoot
		);
		$process->run();

		$this->assertSame(
			0,
			$process->getExitCode(),
			"Legacy probe exited unexpectedly.\nSTDOUT:\n".$process->getOutput()."\nSTDERR:\n".$process->getErrorOutput()
		);

		$decoded = \json_decode( \trim( $process->getOutput() ), true );
		$this->assertIsArray(
			$decoded,
			"Legacy probe did not emit JSON.\nSTDOUT:\n".$process->getOutput()."\nSTDERR:\n".$process->getErrorOutput()
		);

		return $decoded;
	}

	private function normalisePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}
}
