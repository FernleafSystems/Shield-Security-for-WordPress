<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PluginFileCopier;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PluginFileCopier.
 * Focus: Test pattern matching logic that could break packages if wrong.
 */
class PluginFileCopierTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = dirname( dirname( __DIR__ ) );
	}

	private function createFileCopier() :PluginFileCopier {
		return new PluginFileCopier( $this->projectRoot, function ( string $message ) {} );
	}

	// =========================================================================
	// shouldExcludePath() - Pattern matching logic
	// These tests verify the matching algorithm works correctly
	// =========================================================================

	/**
	 * Directory pattern must exclude all contents but not similar names
	 */
	public function testDirectoryPatternMatching() :void {
		$copier = $this->createFileCopier();
		$patterns = [ 'tests' ];

		// Must match: files inside directory
		$this->assertTrue(
			$copier->shouldExcludePath( 'tests/Unit/Test.php', $patterns )
		);
		$this->assertTrue(
			$copier->shouldExcludePath( 'tests/Deep/Nested/Test.php', $patterns )
		);

		// Must match: the directory itself
		$this->assertTrue(
			$copier->shouldExcludePath( 'tests', $patterns )
		);

		// Must NOT match: similar prefixes (this bug would include test files in package)
		$this->assertFalse(
			$copier->shouldExcludePath( 'testing/file.php', $patterns ),
			'Similar prefix "testing" must not match pattern "tests"'
		);
		$this->assertFalse(
			$copier->shouldExcludePath( 'tests-old/file.php', $patterns ),
			'Suffix variation must not match'
		);
	}

	/**
	 * Exact file pattern must match only that file
	 */
	public function testExactFilePatternMatching() :void {
		$copier = $this->createFileCopier();
		$patterns = [ 'README.md' ];

		$this->assertTrue(
			$copier->shouldExcludePath( 'README.md', $patterns )
		);
		$this->assertFalse(
			$copier->shouldExcludePath( 'README.md.bak', $patterns ),
			'Exact pattern must not match files with suffix'
		);
		$this->assertFalse(
			$copier->shouldExcludePath( 'docs/README.md', $patterns ),
			'Exact pattern must not match files in subdirectories'
		);
	}

	/**
	 * Glob patterns must work correctly for selective exclusion
	 */
	public function testGlobPatternMatching() :void {
		$copier = $this->createFileCopier();
		$patterns = [ 'languages/*.po' ];

		// Must match files in directory with extension
		$this->assertTrue(
			$copier->shouldExcludePath( 'languages/en_US.po', $patterns )
		);

		// Must NOT match: wrong extension (would break compiled translations)
		$this->assertFalse(
			$copier->shouldExcludePath( 'languages/en_US.mo', $patterns ),
			'Glob *.po must not match .mo files'
		);

		// Must NOT match: subdirectories (glob * doesn't cross directories)
		$this->assertFalse(
			$copier->shouldExcludePath( 'languages/sub/file.po', $patterns ),
			'Glob * must not cross directory boundaries'
		);
	}

	/**
	 * Windows paths must be normalized
	 */
	public function testWindowsPathNormalization() :void {
		$copier = $this->createFileCopier();

		$this->assertTrue(
			$copier->shouldExcludePath( 'tests\\Unit\\Test.php', [ 'tests' ] )
		);
	}

	// =========================================================================
	// .gitattributes export-ignore - Critical path handling
	// Verify export-ignore patterns correctly include/exclude critical paths
	// =========================================================================

	/**
	 * CRITICAL: Built assets must NOT be ignored
	 * If someone accidentally adds assets/dist to ignore list, packages break
	 */
	public function testBuiltAssetsNotIgnored() :void {
		$copier = $this->createFileCopier();
		$patterns = $copier->getExportIgnorePatterns();

		$this->assertFalse(
			$copier->shouldExcludePath( 'assets/dist/bundle.js', $patterns ),
			'assets/dist/ contains built JS/CSS - must be in package'
		);
		$this->assertFalse(
			$copier->shouldExcludePath( 'assets/dist', $patterns )
		);
	}

	/**
	 * Vendor directories must be ignored (dev deps, not production)
	 */
	public function testVendorDirectoriesIgnored() :void {
		$copier = $this->createFileCopier();
		$patterns = $copier->getExportIgnorePatterns();

		// Root vendor/ contains dev dependencies
		$this->assertTrue(
			$copier->shouldExcludePath( 'vendor/phpunit/phpunit.php', $patterns )
		);

		// But vendor_custom/ (if it existed) should not match
		$this->assertFalse(
			$copier->shouldExcludePath( 'vendor_custom/file.php', $patterns ),
			'Prefix match must not catch similar directory names'
		);
	}

	// =========================================================================
	// getExportIgnorePatterns() - Parser logic
	// Test the parsing, not the file contents
	// =========================================================================

	/**
	 * Leading slashes must be stripped from patterns
	 */
	public function testExportIgnorePatternsNormalized() :void {
		$copier = $this->createFileCopier();
		$patterns = $copier->getExportIgnorePatterns();

		foreach ( $patterns as $pattern ) {
			$this->assertStringStartsNotWith( '/', $pattern, "Pattern '$pattern' must not have leading slash" );
		}
	}

	/**
	 * Integration: verify actual .gitattributes patterns exclude expected paths
	 */
	public function testGitattributesPatternsExcludeExpectedPaths() :void {
		$copier = $this->createFileCopier();
		$patterns = $copier->getExportIgnorePatterns();

		// These paths should be excluded based on typical .gitattributes
		$shouldExclude = [
			'tests/Unit/SomeTest.php',
			'.github/workflows/test.yml',
		];

		foreach ( $shouldExclude as $path ) {
			$this->assertTrue(
				$copier->shouldExcludePath( $path, $patterns ),
				"Path '$path' should be excluded by .gitattributes patterns"
			);
		}

		// These paths must NOT be excluded
		$mustInclude = [
			'src/Controller/Controller.php',
			'icwp-wpsf.php',
			'plugin.json',
		];

		foreach ( $mustInclude as $path ) {
			$this->assertFalse(
				$copier->shouldExcludePath( $path, $patterns ),
				"Path '$path' must NOT be excluded - required in package"
			);
		}
	}
}
