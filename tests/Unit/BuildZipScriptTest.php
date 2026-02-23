<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

/**
 * Tests for the build-zip.php script configuration.
 *
 * Note: The script itself is procedural and best tested via integration tests
 * that actually run `composer build-zip`. These unit tests verify the
 * configuration is correct and catch obvious regressions.
 */
class BuildZipScriptTest extends BaseUnitTest {

	use PluginPathsTrait;

	/**
	 * Verify the script has valid PHP syntax.
	 * Catches syntax errors before they reach CI/production.
	 */
	public function testBuildZipScriptHasValidSyntax() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}
		$scriptPath = $this->getPluginFilePath( 'bin/build-zip.php' );
		$output = [];
		$returnCode = 0;
		\exec( 'php -l '.\escapeshellarg( $scriptPath ).' 2>&1', $output, $returnCode );
		$this->assertSame( 0, $returnCode, 'bin/build-zip.php should have valid PHP syntax: '.\implode( "\n", $output ) );
	}

	/**
	 * Verify composer.json has the build-zip script configured correctly.
	 * This is the user-facing entry point - if this breaks, the feature is broken.
	 */
	public function testComposerScriptExists() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'composer.json is excluded from packages (development-only)' );
		}
		$commands = $this->getComposerScriptCommands( 'build-zip' );

		$this->assertContains(
			'@php bin/build-zip.php',
			$commands,
			'build-zip script should include bin/build-zip.php command'
		);
	}

	/**
	 * Verify the script can be parsed and its dependencies are available.
	 * This catches missing use statements or autoload issues.
	 */
	public function testScriptDependenciesAreAvailable() :void {
		// These classes must exist for the script to work
		$this->assertTrue(
			\class_exists( \FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PluginPackager::class ),
			'PluginPackager class must be available'
		);
		$this->assertTrue(
			\class_exists( \FernleafSystems\ShieldPlatform\Tooling\PluginPackager\SafeDirectoryRemover::class ),
			'SafeDirectoryRemover class must be available'
		);
		$this->assertTrue(
			\class_exists( \Symfony\Component\Filesystem\Path::class ),
			'Symfony Path class must be available'
		);
		$this->assertTrue(
			\class_exists( \ZipArchive::class ),
			'ZipArchive extension must be available'
		);
	}

	public function testSkipRootComposerMapsToComposerInstallOption() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped( 'bin/ directory is excluded from packages (development-only)' );
		}

		$content = $this->getPluginFileContents( 'bin/build-zip.php', 'build-zip script' );

		$this->assertStringContainsString( "'skip-root-composer'", $content );
		$this->assertStringContainsString( "'composer_install' ] = false", $content );
		$this->assertStringNotContainsString( "'composer_root' ] = false", $content );
	}
}
