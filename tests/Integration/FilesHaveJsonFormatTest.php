<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Test that verifies JSON configuration files are valid
 */
class FilesHaveJsonFormatTest extends PolyfillTestCase {

    public function testMainPluginJsonIsValid() :void {
        $pluginJsonPath = dirname( dirname( __DIR__ ) ) . '/plugin.json';
        $this->assertFileExists( $pluginJsonPath, 'plugin.json file should exist' );
        
        $jsonContent = file_get_contents( $pluginJsonPath );
        $this->assertNotEmpty( $jsonContent, 'plugin.json should not be empty' );
        
        $jsonParsed = json_decode( $jsonContent, true );
        $this->assertIsArray( $jsonParsed, 'plugin.json should contain valid JSON that decodes to array' );
        $this->assertEmpty( json_last_error(), 'plugin.json should not have JSON parsing errors' );
        
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
        $changelogPath = dirname( dirname( __DIR__ ) ) . '/cl.json';
        $this->assertFileExists( $changelogPath, 'cl.json file should exist' );
        
        $jsonContent = file_get_contents( $changelogPath );
        $this->assertNotEmpty( $jsonContent, 'cl.json should not be empty' );
        
        $jsonParsed = json_decode( $jsonContent, true );
        $this->assertIsArray( $jsonParsed, 'cl.json should contain valid JSON' );
        $this->assertEmpty( json_last_error(), 'cl.json should not have JSON parsing errors' );
    }

    /**
     * Test that all JSON files in root directory are valid
     */
    public function testAllRootJsonFilesAreValid() :void {
        $rootPath = dirname( dirname( __DIR__ ) );
        
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
            $filePath = $rootPath . '/' . $file;
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
