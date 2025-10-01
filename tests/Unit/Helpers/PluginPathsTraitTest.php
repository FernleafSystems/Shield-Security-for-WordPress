<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class PluginPathsTraitTest extends TestCase {

	use PluginPathsTrait;

	protected function set_up() :void {
		parent::set_up();
		$this->clearPackageEnv();
	}

	protected function tear_down() :void {
		$this->clearPackageEnv();
		parent::tear_down();
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testGetPluginRootDefaultsToSourceDirectory() :void {
		$this->clearPackageEnv();
		$expectedRoot = realpath( __DIR__.'/../../..' );
		$this->assertNotFalse( $expectedRoot, 'Expected project root to resolve' );
		$this->assertSame( $expectedRoot, $this->getPluginRoot(), 'Default plugin root should match project source directory' );
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testGetPluginRootHonoursPackagePath() :void {
		$tempDir = sys_get_temp_dir().'/shield-package-'.uniqid( '', true );
		$this->assertTrue( mkdir( $tempDir ), 'Failed to create temporary package directory' );
		$this->assertNotFalse( file_put_contents( $tempDir.'/icwp-wpsf.php', '<?php // stub plugin file' ), 'Failed to seed plugin file in temporary package directory' );

		putenv( 'SHIELD_PACKAGE_PATH='.$tempDir );
		$_ENV['SHIELD_PACKAGE_PATH'] = $tempDir;
		$_SERVER['SHIELD_PACKAGE_PATH'] = $tempDir;

		$this->assertSame( $tempDir, $this->getPluginRoot(), 'Plugin root should honour SHIELD_PACKAGE_PATH' );
		$this->assertSame( $tempDir.'/icwp-wpsf.php', $this->getPluginFilePath( 'icwp-wpsf.php' ) );

		// Cleanup
		$this->clearPackageEnv();
		unlink( $tempDir.'/icwp-wpsf.php' );
		rmdir( $tempDir );
	}

	public function testGetPluginFileContentsReadsFile() :void {
		$content = $this->getPluginFileContents( 'plugin.json', 'plugin.json' );
		$this->assertNotEmpty( $content, 'plugin.json should contain data' );
	}

	public function testDecodePluginJsonFileMatchesManualDecode() :void {
		$manual = json_decode( $this->getPluginFileContents( 'plugin.json', 'plugin.json manual read' ), true );
		$this->assertSame( $manual, $this->decodePluginJsonFile( 'plugin.json', 'plugin.json' ) );
	}

	public function testGetPluginJsonPathShortcut() :void {
		$this->assertSame( $this->getPluginFilePath( 'plugin.json' ), $this->getPluginJsonPath() );
	}

	private function clearPackageEnv() :void {
		putenv( 'SHIELD_PACKAGE_PATH' );
		unset( $_ENV['SHIELD_PACKAGE_PATH'], $_SERVER['SHIELD_PACKAGE_PATH'] );
	}
}
