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
		$composerJson = $this->decodePluginJsonFile( 'composer.json', 'composer.json' );

		$this->assertArrayHasKey( 'scripts', $composerJson, 'composer.json should have scripts section' );
		$this->assertArrayHasKey( 'build-zip', $composerJson['scripts'], 'composer.json should have build-zip script' );
		$this->assertSame(
			'@php bin/build-zip.php',
			$composerJson['scripts']['build-zip'],
			'build-zip script should point to bin/build-zip.php'
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
}
