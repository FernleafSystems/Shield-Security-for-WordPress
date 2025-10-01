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
     * Test that all JSON files in root directory are valid
     */
    public function testAllRootJsonFilesAreValid() :void {
        $rootPath = $this->getPluginRoot();
        
        // Get all JSON files in root directory
        $jsonFiles = $this->getJsonFiles( $rootPath );
        
        // Exclude files that are already tested specifically
        $excludeFiles = ['plugin.json', 'cl.json'];
        $jsonFiles = array_diff( $jsonFiles, $excludeFiles );
        
        if ( empty( $jsonFiles ) ) {
            $this->markTestSkipped( 'No additional JSON files found in root directory to test.' );
            return;
        }
        
        foreach ( $jsonFiles as $file ) {
            $filePath = $this->getPluginFilePath( $file );
            $jsonContent = file_get_contents( $filePath );
            
            // Test that file can be parsed as JSON
            $jsonParsed = json_decode( $jsonContent, true );
            $lastError = json_last_error();
            
            $this->assertEquals( 
                JSON_ERROR_NONE, 
                $lastError, 
                "File {$file} should contain valid JSON. Error: " . json_last_error_msg() 
            );
            
            // Only check for array/object if parsing succeeded
            if ( $lastError === JSON_ERROR_NONE ) {
                $this->assertThat(
                    $jsonParsed,
                    $this->logicalOr(
                        $this->isType('array'),
                        $this->isNull()
                    ),
                    "File {$file} should decode to array or null"
                );
            }
        }
    }

    private function getJsonFiles( string $path ) :array {
        $allItems = scandir( $path );
        return array_filter( $allItems, function( string $item ) use ( $path ) {
            return !is_dir( $path . '/' . $item ) && pathinfo( $item, PATHINFO_EXTENSION ) === 'json';
        } );
    }
}
