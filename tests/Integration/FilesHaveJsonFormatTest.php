<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Test that verifies JSON configuration files are valid
 */
class FilesHaveJsonFormatTest extends PolyfillTestCase {

	use PluginPathsTrait;

	public function testMainPluginJsonIsValid() :void {
		$jsonParsed = $this->decodePluginJsonFile( 'plugin.json', 'plugin.json' );

		// Verify key structure exists
		$this->assertArrayHasKey( 'properties', $jsonParsed, 'plugin.json should have properties key' );
		$this->assertArrayHasKey( 'requirements', $jsonParsed, 'plugin.json should have requirements key' );
		$this->assertArrayHasKey( 'paths', $jsonParsed, 'plugin.json should have paths key' );

		// Verify nested properties
		$properties = $jsonParsed['properties'];
		$this->assertArrayHasKey( 'slug_plugin', $properties, 'properties should have slug_plugin key' );
		$this->assertArrayHasKey( 'version', $properties, 'properties should have version key' );
		$this->assertArrayHasKey( 'text_domain', $properties, 'properties should have text_domain key' );
	}

	public function testChangelogJsonIsValid() :void {
		$jsonParsed = $this->decodePluginJsonFile( 'cl.json', 'cl.json' );
		$this->assertIsArray( $jsonParsed, 'cl.json should contain valid JSON' );
	}

	/**
	 * Test that development JSON files in root directory are valid.
	 * These files (composer.json, package.json, etc.) are excluded from packages
	 * via .gitattributes export-ignore rules, so this test only runs in source mode.
	 * 
	 * @group source-only
	 */
	public function testDevelopmentJsonFilesAreValid() :void {
		if ( $this->isTestingPackage() ) {
			$this->markTestSkipped(
				'Test skipped: Running in package mode. Development JSON files (composer.json, package.json, package-lock.json, patchwork.json) are excluded from packages via .gitattributes export-ignore rules. This test only runs when testing source code directly.'
			);
			return;
		}

		$rootPath = $this->getPluginRoot();
		$this->assertDirectoryExists( $rootPath );

		// Development JSON files that should exist in source
		$devJsonFiles = [ 'composer.json', 'package.json', 'package-lock.json', 'patchwork.json' ];

		foreach ( $devJsonFiles as $file ) {
			$filePath = $this->getPluginFilePath( $file );
			$this->assertFileExists( $filePath, "{$file} should exist in source directory" );

			$content = file_get_contents( $filePath );
			$this->assertNotFalse( $content, "Should be able to read {$file}" );

			json_decode( $content, true );
			$this->assertSame(
				JSON_ERROR_NONE,
				json_last_error(),
				"{$file} should contain valid JSON: " . json_last_error_msg()
			);
		}
	}
}
