<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class PluginPathsTraitTest extends TestCase {

	use PluginPathsTrait;

	/**
	 * Store original SHIELD_PACKAGE_PATH so we can restore it after tests that modify it.
	 * @var string|false
	 */
	private $originalPackagePath;

	protected function set_up() :void {
		parent::set_up();
		// Preserve any incoming SHIELD_PACKAGE_PATH (e.g., from Docker/CI package-mode runs)
		$this->originalPackagePath = getenv( 'SHIELD_PACKAGE_PATH' );
	}

	protected function tear_down() :void {
		// Restore original env state
		$this->restorePackageEnv();
		parent::tear_down();
	}

	public function testGetPluginRootDefaultsToSourceDirectory() :void {
		// This test explicitly needs source-mode (no package path)
		$this->clearPackageEnv();
		$expectedRoot = realpath( __DIR__.'/../../..' );
		$this->assertNotFalse( $expectedRoot, 'Expected project root to resolve' );
		$this->assertSame( $expectedRoot, $this->getPluginRoot(), 'Default plugin root should match project source directory' );
	}

	public function testGetPluginRootHonoursPackagePath() :void {
		$tempDir = sys_get_temp_dir().'/shield-package-'.uniqid( '', true );
		$this->assertTrue( mkdir( $tempDir ), 'Failed to create temporary package directory' );
		$this->assertNotFalse( file_put_contents( $tempDir.'/icwp-wpsf.php', '<?php // stub plugin file' ), 'Failed to seed plugin file in temporary package directory' );

		putenv( 'SHIELD_PACKAGE_PATH='.$tempDir );
		$_ENV['SHIELD_PACKAGE_PATH'] = $tempDir;
		$_SERVER['SHIELD_PACKAGE_PATH'] = $tempDir;

		$this->assertSame( $tempDir, $this->getPluginRoot(), 'Plugin root should honour SHIELD_PACKAGE_PATH' );
		$this->assertSame( $tempDir.'/icwp-wpsf.php', $this->getPluginFilePath( 'icwp-wpsf.php' ) );

		// Cleanup temp files (env restored by tear_down)
		unlink( $tempDir.'/icwp-wpsf.php' );
		rmdir( $tempDir );
	}

	public function testGetPluginFileContentsReadsFile() :void {
		// Honors SHIELD_PACKAGE_PATH if set (package mode) or falls back to source
		$content = $this->getPluginFileContents( 'plugin.json', 'plugin.json' );
		$this->assertNotEmpty( $content, 'plugin.json should contain data' );
	}

	public function testDecodePluginJsonFileMatchesManualDecode() :void {
		// Honors SHIELD_PACKAGE_PATH if set (package mode) or falls back to source
		$manual = json_decode( $this->getPluginFileContents( 'plugin.json', 'plugin.json manual read' ), true );
		$this->assertSame( $manual, $this->decodePluginJsonFile( 'plugin.json', 'plugin.json' ) );
	}

	public function testGetPluginJsonPathShortcut() :void {
		$this->assertSame( $this->getPluginFilePath( 'plugin.json' ), $this->getPluginJsonPath() );
	}

	public function testGetComposerScriptCommandsNormalizesArrayScriptEntries() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$commands = $this->getComposerScriptCommands( 'package-plugin' );
		$this->assertContains( 'Composer\\Config::disableProcessTimeout', $commands );
		$this->assertContains( '@php bin/package-plugin.php', $commands );
	}

	public function testGetComposerScriptCommandsNormalizesStringScriptEntry() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}

		$this->assertSame(
			[ '@php bin/run-playground-local.php --clean' ],
			$this->getComposerScriptCommands( 'playground:local:clean' )
		);
	}

	/**
	 * Clear SHIELD_PACKAGE_PATH for tests that need source-mode behavior.
	 */
	private function clearPackageEnv() :void {
		putenv( 'SHIELD_PACKAGE_PATH' );
		unset( $_ENV['SHIELD_PACKAGE_PATH'], $_SERVER['SHIELD_PACKAGE_PATH'] );
	}

	/**
	 * Restore SHIELD_PACKAGE_PATH to its original value.
	 */
	private function restorePackageEnv() :void {
		if ( $this->originalPackagePath !== false ) {
			putenv( 'SHIELD_PACKAGE_PATH='.$this->originalPackagePath );
			$_ENV['SHIELD_PACKAGE_PATH'] = $this->originalPackagePath;
			$_SERVER['SHIELD_PACKAGE_PATH'] = $this->originalPackagePath;
		}
		else {
			$this->clearPackageEnv();
		}
	}
}
