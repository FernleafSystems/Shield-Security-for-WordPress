<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PhpSyntaxLintReport;
use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PhpSyntaxLinter;
use FernleafSystems\ShieldPlatform\Tooling\Testing\PreCommitChangedFileLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\SourceStaticAnalysisLane;
use FernleafSystems\ShieldPlatform\Tooling\Testing\UnitTestScriptRunner;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;

class PreCommitChangedFileLaneTest extends TestCase {

	use TempDirLifecycleTrait;

	private string $projectRoot;

	protected function setUp() :void {
		parent::setUp();
		$this->projectRoot = $this->createTrackedTempDir( 'shield-pre-commit-' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testCollectExistingPhpFilesNormalizesAndDeduplicatesChangedPaths() :void {
		$this->writeProjectFile( 'src/Foo.php' );
		$this->writeProjectFile( 'readme.txt', 'plain text' );
		$this->writeProjectFile( 'bin/shield', "#!/usr/bin/env php\n<?php echo 'ok';\n" );

		$lane = new PreCommitChangedFileLane();
		$files = $lane->collectExistingPhpFiles( $this->projectRoot, [
			'src/Foo.php',
			'src\\Foo.php',
			'readme.txt',
			'missing.php',
			'bin/shield',
		] );

		$this->assertSame( [
			'bin/shield',
			'src/Foo.php',
		], $files );
	}

	public function testCollectExistingPhpFilesRejectsOutsideProjectPaths() :void {
		$outsideDir = $this->createTrackedTempDir( 'shield-pre-commit-outside-' );
		$outsideFile = Path::join( $outsideDir, 'Outside.php' );
		\file_put_contents( $outsideFile, "<?php declare( strict_types=1 );\n" );

		$lane = new PreCommitChangedFileLane();

		$this->expectException( \RuntimeException::class );
		$lane->collectExistingPhpFiles( $this->projectRoot, [ $outsideFile ] );
	}

	public function testRunFeedsChangedFilesIntoExistingAnalysisAndUnitTestLanes() :void {
		$this->writeProjectFile( 'src/Changed.php' );
		$this->writeProjectFile( 'bin/build-config.php' );
		$this->writeProjectFile( 'tests/Unit/ChangedTest.php' );
		$this->writeProjectFile( 'tests/Unit/SecondChangedTest.php' );
		$this->writeProjectFile( 'assets/js/app.js', 'console.log("skip");' );

		$linter = new RecordingPreCommitSyntaxLinter();
		$sourceLane = new RecordingPreCommitSourceAnalysisLane();
		$unitRunner = new RecordingPreCommitUnitTestRunner();
		$lane = new PreCommitChangedFileLane( $linter, $sourceLane, $unitRunner );

		$exitCode = $this->runLaneSilenced( $lane, [
			'src/Changed.php',
			'bin/build-config.php',
			'tests/Unit/ChangedTest.php',
			'tests/Unit/SecondChangedTest.php',
			'assets/js/app.js',
		] );

		$this->assertSame( 0, $exitCode );
		$this->assertSame( [
			'bin/build-config.php',
			'src/Changed.php',
			'tests/Unit/ChangedTest.php',
			'tests/Unit/SecondChangedTest.php',
		], $linter->paths );
		$this->assertSame( [ 'src/Changed.php' ], $sourceLane->paths );
		$this->assertSame( [
			'tests/Unit/ChangedTest.php',
			'tests/Unit/SecondChangedTest.php',
		], $unitRunner->args );
	}

	public function testRunSkipsWhenNoChangedPhpFilesRemain() :void {
		$this->writeProjectFile( 'assets/js/app.js', 'console.log("skip");' );

		$linter = new RecordingPreCommitSyntaxLinter();
		$lane = new PreCommitChangedFileLane( $linter );

		$exitCode = $this->runLaneSilenced( $lane, [ 'assets/js/app.js' ] );

		$this->assertSame( 0, $exitCode );
		$this->assertSame( [], $linter->paths );
	}

	/**
	 * @param string[] $paths
	 */
	private function runLaneSilenced( PreCommitChangedFileLane $lane, array $paths ) :int {
		\ob_start();
		try {
			return $lane->run( $this->projectRoot, $paths );
		}
		finally {
			\ob_end_clean();
		}
	}

	private function writeProjectFile( string $relativePath, ?string $content = null ) :void {
		$path = Path::join( $this->projectRoot, $relativePath );
		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) ) {
			\mkdir( $dir, 0777, true );
		}
		\file_put_contents( $path, $content ?? "<?php declare( strict_types=1 );\n" );
	}
}

class RecordingPreCommitSyntaxLinter extends PhpSyntaxLinter {

	/** @var string[] */
	public array $paths = [];

	public function lint( string $rootDir, array $relativePaths ) :PhpSyntaxLintReport {
		$this->paths = $relativePaths;
		return new PhpSyntaxLintReport( \count( $relativePaths ), [] );
	}
}

class RecordingPreCommitSourceAnalysisLane extends SourceStaticAnalysisLane {

	/** @var string[] */
	public array $paths = [];

	public function run( string $rootDir, bool $refreshSetup = false, array $phpStanPaths = [] ) :int {
		$this->paths = $phpStanPaths;
		return 0;
	}
}

class RecordingPreCommitUnitTestRunner extends UnitTestScriptRunner {

	/** @var string[] */
	public array $args = [];

	public function run( array $args, string $rootDir ) :int {
		$this->args = $args;
		return 0;
	}
}
