<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Filesystem\Path;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Test that JSON files in the config directory are valid.
 * 
 * Note: This complements the Integration FilesHaveJsonFormatTest which tests
 * root-level JSON files. This Unit test focuses on config directory JSON files.
 */
class FilesHaveJsonFormatTest extends TestCase {

	use PluginPathsTrait;

	private ?string $configDir = null;

	protected function set_up() :void {
		parent::set_up();

		$this->configDir = $this->resolveConfigDirectory();
	}

	public function testAllConfigFilesHaveValidJsonContent() :void {
		if ( $this->configDir === null ) {
			$this->markTestSkipped( 'No configuration directory found for JSON validation.' );
		}

		$files = $this->getJsonFiles( $this->configDir );
		$this->assertNotEmpty( $files, 'Expected at least one configuration JSON file.' );

		foreach ( $files as $file ) {
			$content = file_get_contents( $file );
			$this->assertNotFalse( $content, 'Failed to read file: '.$file );

			json_decode( $content, true );
			$this->assertSame( JSON_ERROR_NONE, json_last_error(), 'Invalid JSON in file: '.$file.' ('.json_last_error_msg().')' );
		}
	}

	private function resolveConfigDirectory() :?string {
		$candidates = [
			Path::join( $this->getPluginRoot(), 'src/config' ),
		];

		foreach ( $candidates as $candidate ) {
			if ( is_dir( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * @return list<string>
	 */
	private function getJsonFiles( string $directory ) :array {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS ) );
		$files = [];

		foreach ( $iterator as $fileInfo ) {
			if ( $fileInfo->isFile() && strtolower( $fileInfo->getExtension() ) === 'json' ) {
				$files[] = $fileInfo->getPathname();
			}
		}

		return $files;
	}
}
