<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\ConfigMerger;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Tests for the ConfigMerger class.
 *
 * Validates that the merger correctly combines the 17 spec files
 * into a valid plugin.json configuration.
 */
class ConfigMergerTest extends TestCase {

	/**
	 * @var string Path to the plugin-spec directory
	 */
	private string $specDir;

	/**
	 * @var string Path to temporary output file for testing
	 */
	private string $tempOutputPath;

	protected function set_up() :void {
		parent::set_up();
		$this->specDir = dirname( dirname( __DIR__ ) ) . '/plugin-spec';
		$this->tempOutputPath = sys_get_temp_dir() . '/plugin-json-test-' . uniqid() . '.json';
	}

	protected function tear_down() :void {
		if ( file_exists( $this->tempOutputPath ) ) {
			unlink( $this->tempOutputPath );
		}
		parent::tear_down();
	}

	/**
	 * Test that the spec directory exists and contains required files.
	 */
	public function testSpecDirectoryExists() :void {
		$this->assertDirectoryExists( $this->specDir, 'plugin-spec directory must exist' );
	}

	/**
	 * Test that all 17 required spec files exist.
	 */
	public function testAllSpecFilesExist() :void {
		$manifest = ConfigMerger::getFileManifest();
		$this->assertGreaterThan( 0, \count( $manifest ), 'Should have spec files defined' );

		foreach ( $manifest as $filename => $meta ) {
			$filePath = $this->specDir . '/' . $filename;
			$this->assertFileExists( $filePath, "Spec file must exist: {$filename}" );
		}
	}

	/**
	 * Test that merge() returns a valid configuration array.
	 */
	public function testMergeReturnsValidArray() :void {
		$merger = new ConfigMerger();
		$config = $merger->merge( $this->specDir );

		$this->assertIsArray( $config, 'Merged config must be an array' );
		$this->assertNotEmpty( $config, 'Merged config must not be empty' );
	}

	/**
	 * Test that all required top-level keys exist in merged output.
	 */
	public function testMergedConfigHasRequiredKeys() :void {
		$merger = new ConfigMerger();
		$config = $merger->merge( $this->specDir );

		$requiredKeys = [
			'properties',
			'requirements',
			'paths',
			'includes',
			'menu',
			'labels',
			'meta',
			'plugin_meta',
			'action_links',
			'config_spec',
		];

		foreach ( $requiredKeys as $key ) {
			$this->assertArrayHasKey( $key, $config, "Merged config must have key: {$key}" );
		}
	}

	/**
	 * Test that config_spec has all required nested keys.
	 */
	public function testConfigSpecHasRequiredKeys() :void {
		$merger = new ConfigMerger();
		$config = $merger->merge( $this->specDir );

		$this->assertArrayHasKey( 'config_spec', $config );

		$requiredConfigSpecKeys = [
			'modules',
			'sections',
			'options',
			'defs',
			'admin_notices',
			'databases',
			'events',
		];

		foreach ( $requiredConfigSpecKeys as $key ) {
			$this->assertArrayHasKey( $key, $config['config_spec'], "config_spec must have key: {$key}" );
		}
	}

	/**
	 * Test that mergeToFile() creates a valid JSON file.
	 */
	public function testMergeToFileCreatesValidJson() :void {
		$merger = new ConfigMerger();
		$merger->mergeToFile( $this->specDir, $this->tempOutputPath );

		$this->assertFileExists( $this->tempOutputPath, 'Output file must be created' );

		$content = file_get_contents( $this->tempOutputPath );
		$this->assertNotFalse( $content, 'Output file must be readable' );
		$this->assertNotEmpty( $content, 'Output file must not be empty' );

		$decoded = json_decode( $content, true );
		$this->assertSame( JSON_ERROR_NONE, json_last_error(), 'Output must be valid JSON: ' . json_last_error_msg() );
		$this->assertIsArray( $decoded, 'Output JSON must decode to array' );
	}

	/**
	 * Test that output JSON uses pretty print formatting.
	 */
	public function testOutputJsonIsPrettyPrinted() :void {
		$merger = new ConfigMerger();
		$merger->mergeToFile( $this->specDir, $this->tempOutputPath );

		$content = file_get_contents( $this->tempOutputPath );
		
		// Pretty-printed JSON will have newlines and indentation
		$this->assertStringContainsString( "\n", $content, 'Output should be pretty-printed with newlines' );
		$this->assertStringContainsString( '  ', $content, 'Output should be pretty-printed with indentation' );
	}

	/**
	 * Test that output JSON uses unescaped slashes and unicode.
	 */
	public function testOutputJsonUsesUnescapedFormats() :void {
		$merger = new ConfigMerger();
		$config = $merger->merge( $this->specDir );
		$merger->mergeToFile( $this->specDir, $this->tempOutputPath );

		$content = file_get_contents( $this->tempOutputPath );
		
		// Check for unescaped slashes in URLs (should be https:// not https:\/\/)
		if ( isset( $config['labels']['PluginURI'] ) ) {
			$this->assertStringContainsString( 'https://', $content, 'URLs should have unescaped slashes' );
			$this->assertStringNotContainsString( 'https:\\/', $content, 'URLs should not have escaped slashes' );
		}
	}

	/**
	 * Test that sections array is properly merged (concatenation).
	 */
	public function testSectionsArrayIsMerged() :void {
		$merger = new ConfigMerger();
		$config = $merger->merge( $this->specDir );

		$this->assertArrayHasKey( 'sections', $config['config_spec'] );
		$sections = $config['config_spec']['sections'];

		$this->assertIsArray( $sections, 'Sections must be an array' );
		$this->assertGreaterThan( 0, count( $sections ), 'Sections array must not be empty' );

		// Verify structure of first section
		$this->assertArrayHasKey( 'slug', $sections[0], 'Each section must have a slug' );
		$this->assertArrayHasKey( 'module', $sections[0], 'Each section must have a module' );
	}

	/**
	 * Test that options array is properly merged (concatenation).
	 */
	public function testOptionsArrayIsMerged() :void {
		$merger = new ConfigMerger();
		$config = $merger->merge( $this->specDir );

		$this->assertArrayHasKey( 'options', $config['config_spec'] );
		$options = $config['config_spec']['options'];

		$this->assertIsArray( $options, 'Options must be an array' );
		$this->assertGreaterThan( 0, count( $options ), 'Options array must not be empty' );

		// Verify structure of first option
		$this->assertArrayHasKey( 'key', $options[0], 'Each option must have a key' );
	}

	/**
	 * Test that modules object is properly merged (object merge).
	 */
	public function testModulesObjectIsMerged() :void {
		$merger = new ConfigMerger();
		$config = $merger->merge( $this->specDir );

		$this->assertArrayHasKey( 'modules', $config['config_spec'] );
		$modules = $config['config_spec']['modules'];

		$this->assertIsArray( $modules, 'Modules must be an array/object' );
		$this->assertArrayHasKey( 'plugin', $modules, 'Modules must include "plugin"' );
		$this->assertArrayHasKey( 'firewall', $modules, 'Modules must include "firewall"' );
	}

	/**
	 * Test that missing spec directory throws exception.
	 */
	public function testMissingSpecDirectoryThrows() :void {
		$merger = new ConfigMerger();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Spec directory does not exist' );

		$merger->merge( '/non/existent/path' );
	}

	/**
	 * Test that file manifest returns expected structure.
	 */
	public function testGetFileManifestReturnsValidStructure() :void {
		$manifest = ConfigMerger::getFileManifest();

		$this->assertIsArray( $manifest, 'Manifest must be an array' );
		$this->assertGreaterThan( 0, \count( $manifest ), 'Manifest must have entries' );

		foreach ( $manifest as $filename => $meta ) {
			$this->assertIsString( $filename, 'Filename must be a string' );
			$this->assertStringEndsWith( '.json', $filename, 'Filename must end with .json' );
			$this->assertArrayHasKey( 'target', $meta, 'Meta must have target key' );
			$this->assertArrayHasKey( 'type', $meta, 'Meta must have type key' );
			$this->assertContains( $meta['type'], [ 'object', 'array' ], 'Type must be object or array' );
		}
	}
}

