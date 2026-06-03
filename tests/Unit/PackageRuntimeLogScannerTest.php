<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\Testing\PackageRuntimeLogScanner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class PackageRuntimeLogScannerTest extends TestCase {

	use TempDirLifecycleTrait;

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testGlobalFatalIsFindingWithoutShieldScope() :void {
		$file = $this->writeLog( 'PHP Fatal error: Allowed memory size exhausted' );

		$findings = ( new PackageRuntimeLogScanner() )->scanFiles( [ $file ] );

		$this->assertSame( 'global-fatal', $findings[ 0 ][ 'reason' ] ?? null );
	}

	public function testShieldScopedWarningIsFinding() :void {
		$file = $this->writeLog( 'PHP Deprecated: Thing in wp-content/plugins/wp-simple-firewall/icwp-wpsf.php' );

		$findings = ( new PackageRuntimeLogScanner() )->scanFiles( [ $file ] );

		$this->assertSame( 'shield-scoped-error', $findings[ 0 ][ 'reason' ] ?? null );
	}

	public function testUnrelatedWarningIsIgnored() :void {
		$file = $this->writeLog( 'PHP Warning: sample in wp-includes/class-wp-http.php' );

		$findings = ( new PackageRuntimeLogScanner() )->scanFiles( [ $file ] );

		$this->assertSame( [], $findings );
	}

	private function writeLog( string $contents ) :string {
		$dir = $this->createTrackedTempDir( 'shield-runtime-log-' );
		$file = Path::join( $dir, 'log.txt' );
		\file_put_contents( $file, $contents.\PHP_EOL );
		return $file;
	}
}
