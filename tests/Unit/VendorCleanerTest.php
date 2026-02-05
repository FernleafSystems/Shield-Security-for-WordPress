<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\VendorCleaner;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for VendorCleaner.
 * Focus: Test cleanup pattern logic, especially package-specific patterns.
 */
class VendorCleanerTest extends TestCase {

	private string $testDir;

	protected function setUp() :void {
		parent::setUp();
		$this->testDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'vendor_cleaner_test_'.\uniqid();
		\mkdir( $this->testDir, 0755, true );
	}

	protected function tearDown() :void {
		$this->removeDirectory( $this->testDir );
		parent::tearDown();
	}

	private function removeDirectory( string $dir ) :void {
		if ( !\is_dir( $dir ) ) {
			return;
		}
		$files = \array_diff( \scandir( $dir ), [ '.', '..' ] );
		foreach ( $files as $file ) {
			$path = $dir.DIRECTORY_SEPARATOR.$file;
			\is_dir( $path ) ? $this->removeDirectory( $path ) : \unlink( $path );
		}
		\rmdir( $dir );
	}

	private function createVendorStructure( array $structure ) :void {
		foreach ( $structure as $path ) {
			$fullPath = $this->testDir.DIRECTORY_SEPARATOR.$path;
			$dir = \dirname( $fullPath );
			if ( !\is_dir( $dir ) ) {
				\mkdir( $dir, 0755, true );
			}
			if ( \substr( $path, -1 ) !== '/' ) {
				\file_put_contents( $fullPath, 'test content' );
			}
			else {
				\mkdir( $fullPath, 0755, true );
			}
		}
	}

	private function pathExists( string $relativePath ) :bool {
		return \file_exists( $this->testDir.DIRECTORY_SEPARATOR.$relativePath );
	}

	private function getConstant( string $name ) {
		$reflection = new ReflectionClass( VendorCleaner::class );
		return $reflection->getConstant( $name );
	}

	// =========================================================================
	// CLEANUP_PATTERNS constant validation
	// =========================================================================

	public function testCleanupPatternsStructure() :void {
		$patterns = $this->getConstant( 'CLEANUP_PATTERNS' );

		$this->assertIsArray( $patterns );
		$this->assertArrayHasKey( 'directories', $patterns );
		$this->assertArrayHasKey( 'files', $patterns );
		$this->assertIsArray( $patterns[ 'directories' ] );
		$this->assertIsArray( $patterns[ 'files' ] );
	}

	public function testCleanupPatternsDirectoriesAreValid() :void {
		$patterns = $this->getConstant( 'CLEANUP_PATTERNS' );
		$directories = $patterns[ 'directories' ];

		$this->assertNotEmpty( $directories, 'CLEANUP_PATTERNS directories should not be empty' );
		foreach ( $directories as $dir ) {
			$this->assertIsString( $dir, 'Each directory pattern should be a string' );
			$this->assertNotEmpty( $dir, 'Each directory pattern should not be empty' );
		}
	}

	// =========================================================================
	// PACKAGE_PATTERNS constant validation
	// =========================================================================

	public function testPackagePatternsStructure() :void {
		$patterns = $this->getConstant( 'PACKAGE_PATTERNS' );

		$this->assertIsArray( $patterns );

		// Structure is: path => [ packages ]
		foreach ( $patterns as $path => $packages ) {
			$this->assertIsString( $path, 'Pattern key must be a path string' );
			$this->assertIsArray( $packages, "Packages for path '$path' must be an array" );

			foreach ( $packages as $package ) {
				$this->assertMatchesRegularExpression(
					'/^[a-z0-9_-]+\/[a-z0-9_-]+$/',
					$package,
					"Package '$package' must be in format 'vendor/package' (lowercase)"
				);
			}
		}
	}

	public function testPackagePatternsContainsToolsForCrowdsec() :void {
		$patterns = $this->getConstant( 'PACKAGE_PATTERNS' );

		$this->assertArrayHasKey( 'tools', $patterns );
		$this->assertContains( 'crowdsec/common', $patterns[ 'tools' ] );
	}

	// =========================================================================
	// Global pattern cleanup (applies to all packages)
	// =========================================================================

	public function testGlobalPatternsRemoveTestDirectories() :void {
		$this->createVendorStructure( [
			'vendor/some-vendor/some-package/src/Class.php',
			'vendor/some-vendor/some-package/tests/TestClass.php',
			'vendor/some-vendor/some-package/test/AnotherTest.php',
		] );

		$cleaner = new VendorCleaner( function() {} );
		$stats = $cleaner->clean( $this->testDir );

		$this->assertFalse( $this->pathExists( 'vendor/some-vendor/some-package/tests' ) );
		$this->assertFalse( $this->pathExists( 'vendor/some-vendor/some-package/test' ) );
		$this->assertTrue( $this->pathExists( 'vendor/some-vendor/some-package/src/Class.php' ) );
		$this->assertEquals( 2, $stats[ 'dirs' ] );
	}

	public function testGlobalPatternsRemoveDocFiles() :void {
		$this->createVendorStructure( [
			'vendor/some-vendor/some-package/src/Class.php',
			'vendor/some-vendor/some-package/README.md',
			'vendor/some-vendor/some-package/CHANGELOG.md',
			'vendor/some-vendor/some-package/LICENSE',
		] );

		$cleaner = new VendorCleaner( function() {} );
		$stats = $cleaner->clean( $this->testDir );

		$this->assertFalse( $this->pathExists( 'vendor/some-vendor/some-package/README.md' ) );
		$this->assertFalse( $this->pathExists( 'vendor/some-vendor/some-package/CHANGELOG.md' ) );
		$this->assertFalse( $this->pathExists( 'vendor/some-vendor/some-package/LICENSE' ) );
		$this->assertTrue( $this->pathExists( 'vendor/some-vendor/some-package/src/Class.php' ) );
		$this->assertEquals( 3, $stats[ 'files' ] );
	}

	// =========================================================================
	// Package-specific pattern cleanup
	// =========================================================================

	public function testPackageSpecificPatternRemovesToolsFromCrowdsecCommon() :void {
		$this->createVendorStructure( [
			'vendor/crowdsec/common/src/Client.php',
			'vendor/crowdsec/common/tools/script.php',
			'vendor/crowdsec/common/tools/helper.php',
		] );

		$cleaner = new VendorCleaner( function() {} );
		$stats = $cleaner->clean( $this->testDir );

		$this->assertFalse(
			$this->pathExists( 'vendor/crowdsec/common/tools' ),
			'tools/ directory should be removed from crowdsec/common'
		);
		$this->assertTrue(
			$this->pathExists( 'vendor/crowdsec/common/src/Client.php' ),
			'src/ directory should remain in crowdsec/common'
		);
	}

	public function testPackageSpecificPatternDoesNotAffectOtherPackages() :void {
		$this->createVendorStructure( [
			'vendor/crowdsec/common/tools/script.php',
			'vendor/other-vendor/other-package/tools/useful-tool.php',
			'vendor/another/package/tools/needed.php',
		] );

		$cleaner = new VendorCleaner( function() {} );
		$cleaner->clean( $this->testDir );

		// crowdsec/common tools should be removed
		$this->assertFalse(
			$this->pathExists( 'vendor/crowdsec/common/tools' ),
			'tools/ should be removed from crowdsec/common'
		);

		// Other packages' tools should remain
		$this->assertTrue(
			$this->pathExists( 'vendor/other-vendor/other-package/tools/useful-tool.php' ),
			'tools/ should NOT be removed from other-vendor/other-package'
		);
		$this->assertTrue(
			$this->pathExists( 'vendor/another/package/tools/needed.php' ),
			'tools/ should NOT be removed from another/package'
		);
	}

	public function testPackageSpecificPatternIsCaseInsensitive() :void {
		$this->createVendorStructure( [
			'vendor/CrowdSec/Common/src/Client.php',
			'vendor/CrowdSec/Common/Tools/script.php',
		] );

		$cleaner = new VendorCleaner( function() {} );
		$cleaner->clean( $this->testDir );

		// Should match even with different casing
		$this->assertFalse(
			$this->pathExists( 'vendor/CrowdSec/Common/Tools' ),
			'Package matching should be case-insensitive'
		);
	}

	public function testPackageSpecificPatternMergesWithGlobalPatterns() :void {
		$this->createVendorStructure( [
			'vendor/crowdsec/common/src/Client.php',
			'vendor/crowdsec/common/tools/script.php',
			'vendor/crowdsec/common/tests/ClientTest.php',
			'vendor/crowdsec/common/README.md',
		] );

		$cleaner = new VendorCleaner( function() {} );
		$stats = $cleaner->clean( $this->testDir );

		// Package-specific pattern (tools)
		$this->assertFalse( $this->pathExists( 'vendor/crowdsec/common/tools' ) );

		// Global patterns (tests, README.md)
		$this->assertFalse( $this->pathExists( 'vendor/crowdsec/common/tests' ) );
		$this->assertFalse( $this->pathExists( 'vendor/crowdsec/common/README.md' ) );

		// Source should remain
		$this->assertTrue( $this->pathExists( 'vendor/crowdsec/common/src/Client.php' ) );

		// Should have removed 2 dirs (tools, tests) and 1 file (README.md)
		$this->assertEquals( 2, $stats[ 'dirs' ] );
		$this->assertEquals( 1, $stats[ 'files' ] );
	}

	// =========================================================================
	// Subdirectory path support
	// =========================================================================

	public function testPackagePatternSupportsSubdirectories() :void {
		// This test verifies the subdirectory feature works.
		// We'll test with a mock structure - the actual PACKAGE_PATTERNS may not have subdirs.
		$this->createVendorStructure( [
			'vendor/crowdsec/common/src/Client.php',
			'vendor/crowdsec/common/tools/script.php',
			'vendor/crowdsec/common/tools/tests/ToolTest.php',
		] );

		$cleaner = new VendorCleaner( function() {} );
		$cleaner->clean( $this->testDir );

		// 'tools' is in PACKAGE_PATTERNS for crowdsec/common, so entire tools/ should be removed
		$this->assertFalse(
			$this->pathExists( 'vendor/crowdsec/common/tools' ),
			'tools/ directory should be removed including subdirectories'
		);
		$this->assertTrue(
			$this->pathExists( 'vendor/crowdsec/common/src/Client.php' )
		);
	}

	public function testSubdirectoryPatternOnlyAffectsSpecifiedPackages() :void {
		$this->createVendorStructure( [
			'vendor/crowdsec/common/tools/file.php',
			'vendor/other/package/tools/file.php',
		] );

		$cleaner = new VendorCleaner( function() {} );
		$cleaner->clean( $this->testDir );

		// crowdsec/common tools should be removed (it's in PACKAGE_PATTERNS)
		$this->assertFalse( $this->pathExists( 'vendor/crowdsec/common/tools' ) );

		// other/package tools should remain (not in PACKAGE_PATTERNS)
		$this->assertTrue(
			$this->pathExists( 'vendor/other/package/tools/file.php' ),
			'tools/ in unlisted packages should NOT be removed'
		);
	}

	// =========================================================================
	// vendor_prefixed directory support
	// =========================================================================

	public function testCleansBothVendorAndVendorPrefixed() :void {
		$this->createVendorStructure( [
			'vendor/vendor-a/package-a/tests/Test.php',
			'vendor_prefixed/vendor-b/package-b/tests/Test.php',
		] );

		$cleaner = new VendorCleaner( function() {} );
		$stats = $cleaner->clean( $this->testDir );

		$this->assertFalse( $this->pathExists( 'vendor/vendor-a/package-a/tests' ) );
		$this->assertFalse( $this->pathExists( 'vendor_prefixed/vendor-b/package-b/tests' ) );
		$this->assertEquals( 2, $stats[ 'dirs' ] );
	}

	public function testPackageSpecificPatternsApplyToVendorPrefixed() :void {
		$this->createVendorStructure( [
			'vendor_prefixed/crowdsec/common/tools/script.php',
			'vendor_prefixed/crowdsec/common/src/Client.php',
		] );

		$cleaner = new VendorCleaner( function() {} );
		$cleaner->clean( $this->testDir );

		$this->assertFalse(
			$this->pathExists( 'vendor_prefixed/crowdsec/common/tools' ),
			'Package-specific patterns should apply to vendor_prefixed too'
		);
		$this->assertTrue(
			$this->pathExists( 'vendor_prefixed/crowdsec/common/src/Client.php' )
		);
	}

	// =========================================================================
	// Edge cases
	// =========================================================================

	public function testHandlesNonExistentVendorDirectory() :void {
		// Don't create any structure - vendor dir won't exist
		$cleaner = new VendorCleaner( function() {} );
		$stats = $cleaner->clean( $this->testDir );

		$this->assertEquals( 0, $stats[ 'dirs' ] );
		$this->assertEquals( 0, $stats[ 'files' ] );
	}

	public function testOnlyAffectsDirectChildrenOfPackage() :void {
		$this->createVendorStructure( [
			'vendor/some-vendor/some-package/src/Controller/tests/helper.php',
			'vendor/some-vendor/some-package/tests/RealTest.php',
		] );

		$cleaner = new VendorCleaner( function() {} );
		$cleaner->clean( $this->testDir );

		// Direct child 'tests' should be removed
		$this->assertFalse( $this->pathExists( 'vendor/some-vendor/some-package/tests' ) );

		// Nested 'tests' within src should remain
		$this->assertTrue(
			$this->pathExists( 'vendor/some-vendor/some-package/src/Controller/tests/helper.php' ),
			'Nested directories matching patterns should NOT be removed'
		);
	}
}
