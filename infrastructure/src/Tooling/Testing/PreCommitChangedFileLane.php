<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PhpSyntaxLinter;
use Symfony\Component\Filesystem\Path;

class PreCommitChangedFileLane {

	private const SOURCE_ROOT_FILES = [
		'icwp-wpsf.php',
		'plugin_compatibility.php',
		'plugin_init.php',
		'unsupported.php',
	];

	private PhpSyntaxLinter $syntaxLinter;

	private SourceStaticAnalysisLane $sourceAnalysisLane;

	private UnitTestScriptRunner $unitTestRunner;

	public function __construct(
		?PhpSyntaxLinter $syntaxLinter = null,
		?SourceStaticAnalysisLane $sourceAnalysisLane = null,
		?UnitTestScriptRunner $unitTestRunner = null
	) {
		$this->syntaxLinter = $syntaxLinter ?? new PhpSyntaxLinter();
		$this->sourceAnalysisLane = $sourceAnalysisLane ?? new SourceStaticAnalysisLane();
		$this->unitTestRunner = $unitTestRunner ?? new UnitTestScriptRunner();
	}

	/**
	 * @param string[] $paths
	 */
	public function run( string $rootDir, array $paths ) :int {
		$phpFiles = $this->collectExistingPhpFiles( $rootDir, $paths );

		if ( empty( $phpFiles ) ) {
			echo 'Pre-commit: no changed PHP files to check.'.\PHP_EOL;
			return 0;
		}

		echo 'Pre-commit: checking '.\count( $phpFiles ).' changed PHP file(s).'.\PHP_EOL;
		$syntaxReport = $this->syntaxLinter->lint( $rootDir, $phpFiles );
		echo 'PHP syntax lint checked '.$syntaxReport->getCheckedFileCount().' file(s).'.\PHP_EOL;

		if ( $syntaxReport->hasFailures() ) {
			echo 'Pre-commit syntax lint failed.'.\PHP_EOL;
			foreach ( $syntaxReport->getFailures() as $failure ) {
				echo '- '.$failure[ 'path' ].\PHP_EOL;
				echo $failure[ 'output' ].\PHP_EOL;
			}
			return 1;
		}

		$sourceFiles = \array_values( \array_filter( $phpFiles, [ $this, 'isSourceAnalysisPath' ] ) );
		if ( !empty( $sourceFiles ) ) {
			echo 'Pre-commit: running source PHPStan for '.\count( $sourceFiles ).' file(s).'.\PHP_EOL;
			$sourceExitCode = $this->sourceAnalysisLane->run( $rootDir, false, $sourceFiles );
			if ( $sourceExitCode !== 0 ) {
				return $sourceExitCode;
			}
		}

		$unitTestFiles = \array_values( \array_filter( $phpFiles, [ $this, 'isUnitTestPath' ] ) );
		if ( !empty( $unitTestFiles ) ) {
			echo 'Pre-commit: running unit tests for '.\count( $unitTestFiles ).' changed test file(s).'.\PHP_EOL;
			return $this->unitTestRunner->run( $unitTestFiles, $rootDir );
		}

		return 0;
	}

	/**
	 * @param string[] $paths
	 * @return string[]
	 */
	public function collectExistingPhpFiles( string $rootDir, array $paths ) :array {
		$existingPaths = [];
		foreach ( $paths as $path ) {
			$relativePath = $this->normalizeExistingPath( $rootDir, $path );
			if ( $relativePath === null ) {
				continue;
			}
			$existingPaths[ $relativePath ] = $relativePath;
		}

		return $this->syntaxLinter->discoverPhpFiles( $rootDir, \array_values( $existingPaths ) );
	}

	private function normalizeExistingPath( string $rootDir, string $path ) :?string {
		$path = \trim( \str_replace( '\\', '/', $path ) );
		if ( $path === '' ) {
			return null;
		}

		$rootRealPath = \realpath( $rootDir );
		if ( !\is_string( $rootRealPath ) ) {
			throw new \RuntimeException( 'Project root does not exist: '.$rootDir );
		}
		$rootRealPath = \str_replace( '\\', '/', $rootRealPath );

		$absolutePath = Path::isAbsolute( $path ) ? $path : Path::join( $rootRealPath, $path );
		$realPath = \realpath( $absolutePath );
		if ( !\is_string( $realPath ) || !\is_file( $realPath ) ) {
			return null;
		}

		$realPath = \str_replace( '\\', '/', $realPath );
		if ( $realPath !== $rootRealPath && \strpos( $realPath, $rootRealPath.'/' ) !== 0 ) {
			throw new \RuntimeException( 'Changed file path resolves outside the project root: '.$path );
		}

		return \str_replace( '\\', '/', Path::makeRelative( $realPath, $rootRealPath ) );
	}

	private function isSourceAnalysisPath( string $path ) :bool {
		return \strpos( $path, 'src/' ) === 0 || \in_array( $path, self::SOURCE_ROOT_FILES, true );
	}

	private function isUnitTestPath( string $path ) :bool {
		return \strpos( $path, 'tests/Unit/' ) === 0 && \substr( $path, -8 ) === 'Test.php';
	}
}
