<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\FileSystemUtils;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\VersionUpdater;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Tests for the VersionUpdater class.
 */
class VersionUpdaterTest extends TestCase {

	private string $projectRoot;

	private string $tempDir;

	protected function set_up() :void {
		parent::set_up();
		$this->projectRoot = \dirname( \dirname( __DIR__ ) );
		$this->tempDir = \sys_get_temp_dir().'/version-updater-test-'.\uniqid();
		\mkdir( $this->tempDir, 0755, true );
	}

	protected function tear_down() :void {
		if ( \is_dir( $this->tempDir ) ) {
			FileSystemUtils::removeDirectoryRecursive( $this->tempDir );
		}
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Validation Tests
	// -------------------------------------------------------------------------

	public function testValidVersionFormatsAccepted() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );

		// Create minimal plugin.json for update to work
		$this->createMinimalPluginJson();

		$validVersions = [ '1.0', '21.0.102', '1.2.3.4', '0.1', '999.999.999' ];

		foreach ( $validVersions as $version ) {
			$result = $updater->update( $this->tempDir, [ 'version' => $version ] );
			$this->assertSame( $version, $result[ 'version' ], "Version {$version} should be accepted" );
		}
	}

	public function testInvalidVersionFormatsRejected() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );

		$invalidVersions = [
			[ 'v1.0', 'version with v prefix' ],
			[ '1.0-beta', 'version with suffix' ],
			[ 'abc', 'non-numeric version' ],
			[ '', 'empty version' ],
			[ '1', 'single segment version' ],
			[ '1.0.0-rc1', 'version with prerelease' ],
		];

		foreach ( $invalidVersions as [ $version, $description ] ) {
			try {
				$updater->update( $this->tempDir, [ 'version' => $version ] );
				$this->fail( "Expected exception for {$description}: '{$version}'" );
			}
			catch ( \InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'version', \strtolower( $e->getMessage() ) );
			}
		}
	}

	public function testValidTimestampAccepted() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );
		$this->createMinimalPluginJson();

		$validTimestamps = [
			\time(),           // Current time
			\time() + 86400,   // Future timestamp (tomorrow)
			946684800,         // 2000-01-01
			1765370000,        // From existing plugin-spec
		];

		foreach ( $validTimestamps as $timestamp ) {
			$result = $updater->update( $this->tempDir, [ 'release_timestamp' => $timestamp ] );
			$this->assertSame( $timestamp, $result[ 'release_timestamp' ], "Timestamp {$timestamp} should be accepted" );
		}
	}

	public function testInvalidTimestampRejected() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );

		$invalidTimestamps = [
			0          => 'zero timestamp',
			-1         => 'negative timestamp',
			946684799  => 'pre-2000 timestamp',
		];

		foreach ( $invalidTimestamps as $timestamp => $description ) {
			try {
				$updater->update( $this->tempDir, [ 'release_timestamp' => $timestamp ] );
				$this->fail( "Expected exception for {$description}: {$timestamp}" );
			}
			catch ( \InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'timestamp', \strtolower( $e->getMessage() ) );
			}
		}
	}

	public function testValidBuildFormatAccepted() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );
		$this->createMinimalPluginJson();

		$validBuilds = [
			'202602.0301',
			'202512.1001',
			'202001.0101',
			'203012.3199',
		];

		foreach ( $validBuilds as $build ) {
			$result = $updater->update( $this->tempDir, [ 'build' => $build ] );
			$this->assertSame( $build, $result[ 'build' ], "Build {$build} should be accepted" );
		}
	}

	public function testInvalidBuildFormatRejected() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );

		$invalidBuilds = [
			[ '2026-02-03', 'date format with dashes' ],
			[ '202602.1', 'missing digit in day/iteration' ],
			[ '20260203', 'no dot separator' ],
			[ '', 'empty build' ],
			[ 'abc', 'non-numeric build' ],
		];

		foreach ( $invalidBuilds as [ $build, $description ] ) {
			try {
				$updater->update( $this->tempDir, [ 'build' => $build ] );
				$this->fail( "Expected exception for {$description}: '{$build}'" );
			}
			catch ( \InvalidArgumentException $e ) {
				$this->assertStringContainsString( 'build', \strtolower( $e->getMessage() ) );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Build Generation Tests
	// -------------------------------------------------------------------------

	public function testGenerateBuildReturnsCorrectFormat() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );

		$build = $updater->generateBuild();

		// Format: YYYYMM.DDBB
		$this->assertMatchesRegularExpression( '/^\d{6}\.\d{4}$/', $build, 'Build should match YYYYMM.DDBB format' );

		// Verify it contains today's date prefix
		$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$expectedPrefix = $now->format( 'Ym' ).'.'.$now->format( 'd' );
		$this->assertStringStartsWith( $expectedPrefix, $build, 'Build should start with today\'s date' );
	}

	// -------------------------------------------------------------------------
	// File Update Tests
	// -------------------------------------------------------------------------

	public function testUpdatePluginJsonUpdatesAllFields() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );

		$this->createMinimalPluginJson();

		$updater->update( $this->tempDir, [
			'version'           => '21.0.999',
			'release_timestamp' => 1765370999,
			'build'             => '202602.0399',
		] );

		$path = $this->tempDir.'/plugin.json';
		$content = \file_get_contents( $path );
		$config = \json_decode( $content, true );

		$this->assertSame( '21.0.999', $config[ 'properties' ][ 'version' ] );
		$this->assertSame( 1765370999, $config[ 'properties' ][ 'release_timestamp' ] );
		$this->assertSame( '202602.0399', $config[ 'properties' ][ 'build' ] );
	}

	public function testUpdatePluginJsonPreservesOtherFields() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );

		// Create plugin.json with extra fields
		$initialConfig = [
			'properties' => [
				'version'           => '1.0.0',
				'release_timestamp' => 1000000000,
				'build'             => '202001.0101',
				'slug_plugin'       => 'test-plugin',
				'text_domain'       => 'test-domain',
			],
			'other_key' => 'should be preserved',
		];
		\file_put_contents(
			$this->tempDir.'/plugin.json',
			\json_encode( $initialConfig, \JSON_PRETTY_PRINT )
		);

		$updater->update( $this->tempDir, [ 'version' => '2.0.0' ] );

		$content = \file_get_contents( $this->tempDir.'/plugin.json' );
		$config = \json_decode( $content, true );

		// Updated field
		$this->assertSame( '2.0.0', $config[ 'properties' ][ 'version' ] );

		// Preserved fields
		$this->assertSame( 'test-plugin', $config[ 'properties' ][ 'slug_plugin' ] );
		$this->assertSame( 'test-domain', $config[ 'properties' ][ 'text_domain' ] );
		$this->assertSame( 'should be preserved', $config[ 'other_key' ] );
	}

	public function testUpdateReadmeTxtReplacesStableTag() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );
		$this->createMinimalPluginJson();

		// Create readme.txt
		$readmeContent = "=== Test Plugin ===\nStable tag: 1.0.0\n\nDescription here.";
		\file_put_contents( $this->tempDir.'/readme.txt', $readmeContent );

		$updater->update( $this->tempDir, [ 'version' => '2.0.0' ] );

		$content = \file_get_contents( $this->tempDir.'/readme.txt' );
		$this->assertStringContainsString( 'Stable tag: 2.0.0', $content );
		$this->assertStringNotContainsString( 'Stable tag: 1.0.0', $content );
	}

	public function testUpdateReadmeTxtPreservesLineEndings() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );
		$this->createMinimalPluginJson();

		// Create readme.txt with CRLF line endings
		$readmeContent = "=== Test Plugin ===\r\nStable tag: 1.0.0\r\n\r\nDescription here.";
		\file_put_contents( $this->tempDir.'/readme.txt', $readmeContent );

		$updater->update( $this->tempDir, [ 'version' => '2.0.0' ] );

		$content = \file_get_contents( $this->tempDir.'/readme.txt' );

		// Should still have CRLF endings
		$this->assertStringContainsString( "\r\n", $content, 'CRLF line endings should be preserved' );
	}

	public function testUpdatePluginHeaderReplacesVersion() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );
		$this->createMinimalPluginJson();

		// Create icwp-wpsf.php with plugin header
		$pluginContent = <<<'PHP'
<?php
/*
 * Plugin Name: Test Plugin
 * Version: 1.0.0
 * Description: Test
 */
PHP;
		\file_put_contents( $this->tempDir.'/icwp-wpsf.php', $pluginContent );

		$updater->update( $this->tempDir, [ 'version' => '2.0.0' ] );

		$content = \file_get_contents( $this->tempDir.'/icwp-wpsf.php' );
		$this->assertStringContainsString( '* Version: 2.0.0', $content );
		$this->assertStringNotContainsString( '* Version: 1.0.0', $content );
	}

	public function testPartialUpdateOnlyAffectsProvidedFields() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );

		$initialConfig = [
			'properties' => [
				'version'           => '1.0.0',
				'release_timestamp' => 1000000000,
				'build'             => '202001.0101',
			],
		];
		\file_put_contents(
			$this->tempDir.'/plugin.json',
			\json_encode( $initialConfig, \JSON_PRETTY_PRINT )
		);

		// Only update build
		$updater->update( $this->tempDir, [ 'build' => '202602.0199' ] );

		$content = \file_get_contents( $this->tempDir.'/plugin.json' );
		$config = \json_decode( $content, true );

		// Build updated
		$this->assertSame( '202602.0199', $config[ 'properties' ][ 'build' ] );

		// Other fields preserved
		$this->assertSame( '1.0.0', $config[ 'properties' ][ 'version' ] );
		$this->assertSame( 1000000000, $config[ 'properties' ][ 'release_timestamp' ] );
	}

	public function testNoUpdateWhenEmptyOptions() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );

		$result = $updater->update( $this->tempDir, [] );

		$this->assertEmpty( $result );
	}

	// -------------------------------------------------------------------------
	// Edge Case Tests
	// -------------------------------------------------------------------------

	public function testMissingPluginJsonThrowsException() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'plugin.json not found' );

		$updater->update( $this->tempDir, [ 'version' => '1.0.0' ] );
	}

	public function testMalformedPluginJsonThrowsException() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );

		// Create malformed plugin.json
		\file_put_contents( $this->tempDir.'/plugin.json', '{ invalid json' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to parse plugin.json' );

		$updater->update( $this->tempDir, [ 'version' => '1.0.0' ] );
	}

	public function testMissingReadmeTxtLogsWarning() :void {
		$logged = [];
		$logger = static function ( string $msg ) use ( &$logged ) :void {
			$logged[] = $msg;
		};

		$updater = new VersionUpdater( $this->projectRoot, $logger );
		$this->createMinimalPluginJson();

		// Don't create readme.txt

		$updater->update( $this->tempDir, [ 'version' => '1.0.0' ] );

		$logText = \implode( "\n", $logged );
		$this->assertStringContainsString( 'readme.txt not found', $logText );
	}

	public function testMissingPluginHeaderLogsWarning() :void {
		$logged = [];
		$logger = static function ( string $msg ) use ( &$logged ) :void {
			$logged[] = $msg;
		};

		$updater = new VersionUpdater( $this->projectRoot, $logger );
		$this->createMinimalPluginJson();

		// Don't create icwp-wpsf.php

		$updater->update( $this->tempDir, [ 'version' => '1.0.0' ] );

		$logText = \implode( "\n", $logged );
		$this->assertStringContainsString( 'icwp-wpsf.php not found', $logText );
	}

	public function testJsonOutputUsesCorrectFormatting() :void {
		$updater = new VersionUpdater( $this->projectRoot, static fn() => null );

		$initialConfig = [
			'properties' => [
				'version' => '1.0.0',
			],
			'urls' => [
				'homepage' => 'https://example.com/path',
			],
		];
		\file_put_contents(
			$this->tempDir.'/plugin.json',
			\json_encode( $initialConfig )
		);

		$updater->update( $this->tempDir, [ 'version' => '2.0.0' ] );

		$content = \file_get_contents( $this->tempDir.'/plugin.json' );

		// Should be pretty-printed
		$this->assertStringContainsString( "\n", $content );

		// Should have unescaped slashes
		$this->assertStringContainsString( 'https://example.com/path', $content );
		$this->assertStringNotContainsString( 'https:\\/\\/', $content );

		// Should end with newline
		$this->assertStringEndsWith( "\n", $content );
	}

	// -------------------------------------------------------------------------
	// updateSourceProperties() Tests
	// -------------------------------------------------------------------------

	public function testUpdateSourcePropertiesUpdatesAllFields() :void {
		// Use tempDir as projectRoot so we can create plugin-spec there
		$updater = new VersionUpdater( $this->tempDir, static fn() => null );

		$this->createMinimalSourceProperties();

		$updater->updateSourceProperties( [
			'version'           => '21.0.999',
			'release_timestamp' => 1765370999,
			'build'             => '202602.0399',
		] );

		$path = $this->tempDir.'/plugin-spec/01_properties.json';
		$content = \file_get_contents( $path );
		$data = \json_decode( $content, true );

		$this->assertSame( '21.0.999', $data[ 'version' ] );
		$this->assertSame( 1765370999, $data[ 'release_timestamp' ] );
		$this->assertSame( '202602.0399', $data[ 'build' ] );
	}

	public function testUpdateSourcePropertiesPreservesOtherFields() :void {
		$updater = new VersionUpdater( $this->tempDir, static fn() => null );

		// Create properties with extra fields
		$specDir = $this->tempDir.'/plugin-spec';
		\mkdir( $specDir, 0755, true );
		$initialData = [
			'version'           => '1.0.0',
			'release_timestamp' => 1000000000,
			'build'             => '202001.0101',
			'slug_plugin'       => 'test-plugin',
			'text_domain'       => 'test-domain',
		];
		\file_put_contents(
			$specDir.'/01_properties.json',
			\json_encode( $initialData, \JSON_PRETTY_PRINT )
		);

		$updater->updateSourceProperties( [ 'version' => '2.0.0' ] );

		$content = \file_get_contents( $specDir.'/01_properties.json' );
		$data = \json_decode( $content, true );

		// Updated field
		$this->assertSame( '2.0.0', $data[ 'version' ] );

		// Preserved fields
		$this->assertSame( 'test-plugin', $data[ 'slug_plugin' ] );
		$this->assertSame( 'test-domain', $data[ 'text_domain' ] );
	}

	public function testUpdateSourcePropertiesMissingFileThrowsException() :void {
		$updater = new VersionUpdater( $this->tempDir, static fn() => null );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Source properties file not found' );

		$updater->updateSourceProperties( [ 'version' => '1.0.0' ] );
	}

	public function testUpdateSourcePropertiesMalformedJsonThrowsException() :void {
		$updater = new VersionUpdater( $this->tempDir, static fn() => null );

		$specDir = $this->tempDir.'/plugin-spec';
		\mkdir( $specDir, 0755, true );
		\file_put_contents( $specDir.'/01_properties.json', '{ invalid json' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to parse source properties' );

		$updater->updateSourceProperties( [ 'version' => '1.0.0' ] );
	}

	public function testUpdateSourcePropertiesDoesNothingWithEmptyOptions() :void {
		$updater = new VersionUpdater( $this->tempDir, static fn() => null );

		$this->createMinimalSourceProperties();
		$originalContent = \file_get_contents( $this->tempDir.'/plugin-spec/01_properties.json' );

		$updater->updateSourceProperties( [] );

		$newContent = \file_get_contents( $this->tempDir.'/plugin-spec/01_properties.json' );
		$this->assertSame( $originalContent, $newContent );
	}

	public function testUpdateSourcePropertiesValidatesVersion() :void {
		$updater = new VersionUpdater( $this->tempDir, static fn() => null );

		$this->createMinimalSourceProperties();

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'version' );

		$updater->updateSourceProperties( [ 'version' => 'invalid' ] );
	}

	public function testUpdateSourcePropertiesValidatesTimestamp() :void {
		$updater = new VersionUpdater( $this->tempDir, static fn() => null );

		$this->createMinimalSourceProperties();

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'timestamp' );

		$updater->updateSourceProperties( [ 'release_timestamp' => -1 ] );
	}

	public function testUpdateSourcePropertiesValidatesBuild() :void {
		$updater = new VersionUpdater( $this->tempDir, static fn() => null );

		$this->createMinimalSourceProperties();

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'build' );

		$updater->updateSourceProperties( [ 'build' => 'invalid' ] );
	}

	public function testUpdateSourcePropertiesJsonOutputUsesCorrectFormatting() :void {
		$updater = new VersionUpdater( $this->tempDir, static fn() => null );

		$specDir = $this->tempDir.'/plugin-spec';
		\mkdir( $specDir, 0755, true );
		$initialData = [
			'version' => '1.0.0',
			'urls'    => [
				'homepage' => 'https://example.com/path',
			],
		];
		\file_put_contents(
			$specDir.'/01_properties.json',
			\json_encode( $initialData )
		);

		$updater->updateSourceProperties( [ 'version' => '2.0.0' ] );

		$content = \file_get_contents( $specDir.'/01_properties.json' );

		// Should be pretty-printed
		$this->assertStringContainsString( "\n", $content );

		// Should have unescaped slashes
		$this->assertStringContainsString( 'https://example.com/path', $content );
		$this->assertStringNotContainsString( 'https:\\/\\/', $content );

		// Should end with newline
		$this->assertStringEndsWith( "\n", $content );
	}

	// -------------------------------------------------------------------------
	// Helper Methods
	// -------------------------------------------------------------------------

	private function createMinimalPluginJson() :void {
		$config = [
			'properties' => [
				'version'           => '1.0.0',
				'release_timestamp' => 1000000000,
				'build'             => '202001.0101',
			],
		];
		\file_put_contents(
			$this->tempDir.'/plugin.json',
			\json_encode( $config, \JSON_PRETTY_PRINT )
		);
	}

	private function createMinimalSourceProperties() :void {
		$specDir = $this->tempDir.'/plugin-spec';
		if ( !\is_dir( $specDir ) ) {
			\mkdir( $specDir, 0755, true );
		}
		$data = [
			'version'           => '1.0.0',
			'release_timestamp' => 1000000000,
			'build'             => '202001.0101',
		];
		\file_put_contents(
			$specDir.'/01_properties.json',
			\json_encode( $data, \JSON_PRETTY_PRINT )
		);
	}
}
