<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PluginFileCopier;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\RecordingProcessRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Unit tests for PluginFileCopier.
 * Focus: Test pattern matching and package copy behavior that could break release artifacts.
 */
class PluginFileCopierTest extends TestCase {

	private string $projectRoot;

	private Filesystem $filesystem;

	/** @var string[] */
	private array $tempDirs = [];

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = dirname( dirname( __DIR__ ) );
		$this->filesystem = new Filesystem();
	}

	protected function tearDown() :void {
		parent::tearDown();

		foreach ( $this->tempDirs as $tempDir ) {
			if ( is_dir( $tempDir ) ) {
				$this->filesystem->remove( $tempDir );
			}
		}
	}

	private function createFileCopier(
		?string $projectRoot = null,
		?RecordingProcessRunner $processRunner = null,
		?callable $logger = null
	) :PluginFileCopier {
		return new PluginFileCopier(
			$projectRoot ?? $this->projectRoot,
			$logger ?? static function () :void {
			},
			$processRunner
		);
	}

	private function createTempDir( string $suffix ) :string {
		$path = Path::join( sys_get_temp_dir(), 'shield-plugin-file-copier-'.$suffix.'-'.bin2hex( random_bytes( 6 ) ) );
		$this->filesystem->mkdir( $path );
		$this->tempDirs[] = $path;

		return $path;
	}

	// =========================================================================
	// shouldExcludePath() - Pattern matching logic
	// =========================================================================

	public function testDirectoryPatternMatching() :void {
		$copier = $this->createFileCopier();
		$patterns = [ 'tests' ];

		$this->assertTrue(
			$copier->shouldExcludePath( 'tests/Unit/Test.php', $patterns )
		);
		$this->assertTrue(
			$copier->shouldExcludePath( 'tests/Deep/Nested/Test.php', $patterns )
		);
		$this->assertTrue(
			$copier->shouldExcludePath( 'tests', $patterns )
		);
		$this->assertFalse(
			$copier->shouldExcludePath( 'testing/file.php', $patterns ),
			'Similar prefix "testing" must not match pattern "tests"'
		);
		$this->assertFalse(
			$copier->shouldExcludePath( 'tests-old/file.php', $patterns ),
			'Suffix variation must not match'
		);
	}

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

	public function testGlobPatternMatching() :void {
		$copier = $this->createFileCopier();
		$patterns = [ 'languages/*.po' ];

		$this->assertTrue(
			$copier->shouldExcludePath( 'languages/en_US.po', $patterns )
		);
		$this->assertFalse(
			$copier->shouldExcludePath( 'languages/en_US.mo', $patterns ),
			'Glob *.po must not match .mo files'
		);
		$this->assertFalse(
			$copier->shouldExcludePath( 'languages/sub/file.po', $patterns ),
			'Glob * must not cross directory boundaries'
		);
	}

	public function testWindowsPathNormalization() :void {
		$copier = $this->createFileCopier();

		$this->assertTrue(
			$copier->shouldExcludePath( 'tests\\Unit\\Test.php', [ 'tests' ] )
		);
	}

	// =========================================================================
	// .gitattributes export-ignore - Critical path handling
	// =========================================================================

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

	public function testVendorDirectoriesIgnored() :void {
		$copier = $this->createFileCopier();
		$patterns = $copier->getExportIgnorePatterns();

		$this->assertTrue(
			$copier->shouldExcludePath( 'vendor/phpunit/phpunit.php', $patterns )
		);
		$this->assertFalse(
			$copier->shouldExcludePath( 'vendor_custom/file.php', $patterns ),
			'Prefix match must not catch similar directory names'
		);
	}

	public function testSrcLibCompatibilityTreeIgnoredAsPackagerOwnedOutput() :void {
		$copier = $this->createFileCopier();
		$patterns = $copier->getExportIgnorePatterns();

		$this->assertTrue(
			$copier->shouldExcludePath( 'src/lib/LegacyProbe/CompatTarget.php', $patterns )
		);
		$this->assertTrue(
			$copier->shouldExcludePath( 'src/lib', $patterns )
		);
		$this->assertFalse(
			$copier->shouldExcludePath( 'src/library/CompatTarget.php', $patterns ),
			'Prefix match must not catch similarly named paths outside the packager-owned subtree'
		);
	}

	// =========================================================================
	// getExportIgnorePatterns() - Parser logic
	// =========================================================================

	public function testExportIgnorePatternsNormalized() :void {
		$copier = $this->createFileCopier();
		$patterns = $copier->getExportIgnorePatterns();

		foreach ( $patterns as $pattern ) {
			$this->assertStringStartsNotWith( '/', $pattern, "Pattern '$pattern' must not have leading slash" );
		}
	}

	public function testGitattributesPatternsExcludeExpectedPaths() :void {
		$copier = $this->createFileCopier();
		$patterns = $copier->getExportIgnorePatterns();

		$shouldExclude = [
			'tests/Unit/SomeTest.php',
			'.github/workflows/test.yml',
			'test-results/junit.xml',
			'playwright.config.js',
			'phpstan-baseline.neon',
			'phpstan.neon.dist',
			'phpstan.package.neon.dist',
			'phpstan.tooling.neon.dist',
		];

		foreach ( $shouldExclude as $path ) {
			$this->assertTrue(
				$copier->shouldExcludePath( $path, $patterns ),
				"Path '$path' should be excluded by .gitattributes patterns"
			);
		}

		$mustInclude = [
			'src/Controller/Controller.php',
			'icwp-wpsf.php',
			'cl.json',
		];

		foreach ( $mustInclude as $path ) {
			$this->assertFalse(
				$copier->shouldExcludePath( $path, $patterns ),
				"Path '$path' must NOT be excluded - required in package"
			);
		}
	}

	// =========================================================================
	// copy() - Production-path behavior
	// =========================================================================

	public function testCopyExcludesIgnoredLocalArtifactsButKeepsAllowlistedAssets() :void {
		$projectRoot = $this->createTempDir( 'project' );
		$targetDir = $this->createTempDir( 'target' );
		$processRunner = new RecordingProcessRunner( [
			[
				'exit_code' => 0,
				'stdout' => "assets/dist/\0playwright-report/\0",
			],
		] );

		$this->filesystem->dumpFile( Path::join( $projectRoot, '.gitattributes' ), '' );
		$this->filesystem->dumpFile( Path::join( $projectRoot, 'icwp-wpsf.php' ), '<?php' );
		$this->filesystem->dumpFile( Path::join( $projectRoot, 'src', 'Controller.php' ), '<?php' );
		$this->filesystem->dumpFile( Path::join( $projectRoot, 'assets', 'dist', 'bundle.js' ), 'console.log("ok");' );
		$this->filesystem->dumpFile( Path::join( $projectRoot, 'playwright-report', 'index.html' ), '<html></html>' );
		$this->filesystem->mkdir( Path::join( $projectRoot, '.git' ) );

		$copier = $this->createFileCopier( $projectRoot, $processRunner );
		$stats = $copier->copy( $targetDir );

		$this->assertGreaterThan( 0, $stats[ 'files' ] );
		$this->assertFileExists( Path::join( $targetDir, 'icwp-wpsf.php' ) );
		$this->assertFileExists( Path::join( $targetDir, 'src', 'Controller.php' ) );
		$this->assertFileExists( Path::join( $targetDir, 'assets', 'dist', 'bundle.js' ) );
		$this->assertFileDoesNotExist( Path::join( $targetDir, 'playwright-report', 'index.html' ) );
		$this->assertSame(
			[
				'git',
				'ls-files',
				'--others',
				'--ignored',
				'--exclude-standard',
				'--directory',
				'-z',
			],
			$processRunner->calls[ 0 ][ 'command' ]
		);
	}

	public function testCopyFallsBackWhenIgnoredPathDiscoveryFails() :void {
		$projectRoot = $this->createTempDir( 'project' );
		$targetDir = $this->createTempDir( 'target' );
		$processRunner = new RecordingProcessRunner( [
			[
				'exit_code' => 1,
				'stderr' => 'fatal: not a git repository',
			],
		] );
		$logs = [];

		$this->filesystem->dumpFile( Path::join( $projectRoot, '.gitattributes' ), '' );
		$this->filesystem->dumpFile( Path::join( $projectRoot, 'icwp-wpsf.php' ), '<?php' );
		$this->filesystem->mkdir( Path::join( $projectRoot, '.git' ) );

		$copier = $this->createFileCopier(
			$projectRoot,
			$processRunner,
			static function ( string $message ) use ( &$logs ) :void {
				$logs[] = $message;
			}
		);

		$copier->copy( $targetDir );

		$this->assertFileExists( Path::join( $targetDir, 'icwp-wpsf.php' ) );
		$this->assertTrue(
			$this->logsContain( $logs, 'Warning: git ignored-path inspection failed with exit code 1' )
		);
	}

	/**
	 * @param string[] $logs
	 */
	private function logsContain( array $logs, string $needle ) :bool {
		foreach ( $logs as $line ) {
			if ( strpos( $line, $needle ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
