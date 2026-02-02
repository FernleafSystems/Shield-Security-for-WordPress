<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PluginPackager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for PluginPackager.
 * Focus: Test OUR logic that could break packages if wrong.
 */
class PluginPackagerTest extends TestCase {

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = dirname( dirname( __DIR__ ) );
	}

	private function invokePrivateMethod( object $object, string $methodName, array $args = [] ) {
		$reflection = new ReflectionClass( $object );
		$method = $reflection->getMethod( $methodName );
		$method->setAccessible( true );
		return $method->invokeArgs( $object, $args );
	}

	private function createPackager() :PluginPackager {
		return new PluginPackager( $this->projectRoot, function( string $message ) {} );
	}

	// =========================================================================
	// shouldExcludePath() - Pattern matching logic
	// These tests verify the matching algorithm works correctly
	// =========================================================================

	/**
	 * Directory pattern must exclude all contents but not similar names
	 */
	public function testDirectoryPatternMatching() :void {
		$packager = $this->createPackager();
		$patterns = [ 'tests' ];

		// Must match: files inside directory
		$this->assertTrue(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'tests/Unit/Test.php', $patterns ] )
		);
		$this->assertTrue(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'tests/Deep/Nested/Test.php', $patterns ] )
		);

		// Must match: the directory itself
		$this->assertTrue(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'tests', $patterns ] )
		);

		// Must NOT match: similar prefixes (this bug would include test files in package)
		$this->assertFalse(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'testing/file.php', $patterns ] ),
			'Similar prefix "testing" must not match pattern "tests"'
		);
		$this->assertFalse(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'tests-old/file.php', $patterns ] ),
			'Suffix variation must not match'
		);
	}

	/**
	 * Exact file pattern must match only that file
	 */
	public function testExactFilePatternMatching() :void {
		$packager = $this->createPackager();
		$patterns = [ 'README.md' ];

		$this->assertTrue(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'README.md', $patterns ] )
		);
		$this->assertFalse(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'README.md.bak', $patterns ] ),
			'Exact pattern must not match files with suffix'
		);
		$this->assertFalse(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'docs/README.md', $patterns ] ),
			'Exact pattern must not match files in subdirectories'
		);
	}

	/**
	 * Glob patterns must work correctly for selective exclusion
	 */
	public function testGlobPatternMatching() :void {
		$packager = $this->createPackager();
		$patterns = [ 'languages/*.po' ];

		// Must match files in directory with extension
		$this->assertTrue(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'languages/en_US.po', $patterns ] )
		);

		// Must NOT match: wrong extension (would break compiled translations)
		$this->assertFalse(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'languages/en_US.mo', $patterns ] ),
			'Glob *.po must not match .mo files'
		);

		// Must NOT match: subdirectories (glob * doesn't cross directories)
		$this->assertFalse(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'languages/sub/file.po', $patterns ] ),
			'Glob * must not cross directory boundaries'
		);
	}

	/**
	 * Windows paths must be normalized
	 */
	public function testWindowsPathNormalization() :void {
		$packager = $this->createPackager();

		$this->assertTrue(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'tests\\Unit\\Test.php', [ 'tests' ] ] )
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
		$packager = $this->createPackager();
		$patterns = $this->invokePrivateMethod( $packager, 'getExportIgnorePatterns', [] );

		$this->assertFalse(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'assets/dist/bundle.js', $patterns ] ),
			'assets/dist/ contains built JS/CSS - must be in package'
		);
		$this->assertFalse(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'assets/dist', $patterns ] )
		);
	}

	/**
	 * Vendor directories must be ignored (dev deps, not production)
	 */
	public function testVendorDirectoriesIgnored() :void {
		$packager = $this->createPackager();
		$patterns = $this->invokePrivateMethod( $packager, 'getExportIgnorePatterns', [] );

		// Root vendor/ contains dev dependencies
		$this->assertTrue(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'vendor/phpunit/phpunit.php', $patterns ] )
		);

		// But vendor_custom/ (if it existed) should not match
		$this->assertFalse(
			$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ 'vendor_custom/file.php', $patterns ] ),
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
		$packager = $this->createPackager();
		$patterns = $this->invokePrivateMethod( $packager, 'getExportIgnorePatterns', [] );

		foreach ( $patterns as $pattern ) {
			$this->assertStringStartsNotWith( '/', $pattern, "Pattern '$pattern' must not have leading slash" );
		}
	}

	/**
	 * Integration: verify actual .gitattributes patterns exclude expected paths
	 */
	public function testGitattributesPatternsExcludeExpectedPaths() :void {
		$packager = $this->createPackager();
		$patterns = $this->invokePrivateMethod( $packager, 'getExportIgnorePatterns', [] );

		// These paths should be excluded based on typical .gitattributes
		$shouldExclude = [
			'tests/Unit/SomeTest.php',
			'.github/workflows/test.yml',
		];

		foreach ( $shouldExclude as $path ) {
			$this->assertTrue(
				$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ $path, $patterns ] ),
				"Path '$path' should be excluded by .gitattributes patterns"
			);
		}

		// These paths must NOT be excluded
		$mustInclude = [
			'src/lib/src/Controller/Controller.php',
			'icwp-wpsf.php',
			'plugin.json',
		];

		foreach ( $mustInclude as $path ) {
			$this->assertFalse(
				$this->invokePrivateMethod( $packager, 'shouldExcludePath', [ $path, $patterns ] ),
				"Path '$path' must NOT be excluded - required in package"
			);
		}
	}

	// =========================================================================
	// resolveOutputDirectory() - Required parameter validation
	// =========================================================================

	/**
	 * @dataProvider providerInvalidOutputDirectory
	 */
	public function testOutputDirectoryRequired( $input ) :void {
		$packager = $this->createPackager();

		$this->expectException( \RuntimeException::class );
		$this->invokePrivateMethod( $packager, 'resolveOutputDirectory', [ $input ] );
	}

	public static function providerInvalidOutputDirectory() :array {
		return [
			'empty string' => [ '' ],
			'null'         => [ null ],
			'whitespace'   => [ '   ' ],
			'quotes only'  => [ '""' ],
		];
	}
}
