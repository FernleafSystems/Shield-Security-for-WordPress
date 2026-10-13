<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

class UnitTestScriptRunner {

	private ProcessRunner $processRunner;

	private UnitTestExecutionSelector $selector;

	public function __construct(
		?ProcessRunner $processRunner = null,
		?UnitTestExecutionSelector $selector = null
	) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
		$this->selector = $selector ?? new UnitTestExecutionSelector();
	}

	/**
	 * @param string[] $args
	 */
	public function run( array $args, string $rootDir ) :int {
		[ $mode, $forwardArgs ] = $this->extractModeAndArgs( $args );
		$junitTarget = $this->extractJUnitTarget( $forwardArgs, $rootDir );
		$failOnEmptyTestSuite = \in_array( '--fail-on-empty-test-suite', $forwardArgs, true );
		$forwardArgs = $this->withoutCoordinatedOptions( $forwardArgs );
		$hasExplicitOperatorPath = $this->hasExplicitOperatorPath( $forwardArgs, $rootDir );
		$reportDirectory = ( $junitTarget !== null || $failOnEmptyTestSuite )
			? $this->createReportDirectory( $rootDir )
			: null;
		$reportPaths = [];
		$exitCode = 0;

		try {
			if ( $this->shouldRunOperatorSuite( $forwardArgs, $rootDir ) ) {
				$exitCode = $this->runCommand(
					$this->buildOperatorCommand( $this->withPartitionReport( $forwardArgs, $reportDirectory, $reportPaths ), $rootDir ),
					$rootDir,
					$exitCode
				);
			}

			if ( $hasExplicitOperatorPath ) {
				$forwardArgs = $this->withoutExplicitOperatorPath( $forwardArgs, $rootDir );
			}

			if ( $this->concretePathArgumentIndexes( $forwardArgs, $rootDir ) !== [] || !$hasExplicitOperatorPath ) {
				$splitArgs = $this->splitParatestConcretePathRuns( $forwardArgs, $mode, $rootDir ) ?? [ $forwardArgs ];
				foreach ( $splitArgs as $runArgs ) {
					$exitCode = $this->runCommand(
						$this->buildCommand( $this->withPartitionReport( $runArgs, $reportDirectory, $reportPaths ), $mode, $rootDir ),
						$rootDir,
						$exitCode
					);
				}
			}

			if ( $junitTarget !== null ) {
				$this->mergeJUnitReports( $reportPaths, $junitTarget );
			}
			if ( $failOnEmptyTestSuite && $this->countJUnitTests( $reportPaths ) === 0 ) {
				return 1;
			}
			return $exitCode;
		}
		finally {
			$this->removeReportDirectory( $reportDirectory, $reportPaths );
		}
	}

	/** @param string[] $command */
	private function runCommand( array $command, string $rootDir, int $currentExitCode ) :int {
		$exitCode = $this->processRunner->runForExitCode( $command, $rootDir );
		return $currentExitCode === 0 ? $exitCode : $currentExitCode;
	}

	/** @param string[] $args */
	private function extractJUnitTarget( array $args, string $rootDir ) :?string {
		$targets = [];
		for ( $index = 0; $index < \count( $args ); $index++ ) {
			if ( $args[ $index ] === '--log-junit' && isset( $args[ $index + 1 ] ) ) {
				$targets[] = $args[ ++$index ];
			}
			elseif ( \str_starts_with( $args[ $index ], '--log-junit=' ) ) {
				$targets[] = \substr( $args[ $index ], 12 );
			}
		}
		if ( \count( $targets ) > 1 ) {
			throw new \InvalidArgumentException( 'Specify only one --log-junit destination for a split unit run.' );
		}
		return $targets === [] ? null : Path::makeAbsolute( $targets[ 0 ], $rootDir );
	}

	/**
	 * @param string[] $args
	 * @return string[]
	 */
	private function withoutCoordinatedOptions( array $args ) :array {
		$filtered = [];
		for ( $index = 0; $index < \count( $args ); $index++ ) {
			if ( $args[ $index ] === '--fail-on-empty-test-suite' ) {
				continue;
			}
			if ( $args[ $index ] === '--log-junit' ) {
				$index++;
				continue;
			}
			if ( \str_starts_with( $args[ $index ], '--log-junit=' ) ) {
				continue;
			}
			$filtered[] = $args[ $index ];
		}
		return $filtered;
	}

	/**
	 * @param string[] $args
	 * @param string[] $reportPaths
	 * @return string[]
	 */
	private function withPartitionReport( array $args, ?string $reportDirectory, array &$reportPaths ) :array {
		if ( $reportDirectory === null ) {
			return $args;
		}
		$path = Path::join( $reportDirectory, \count( $reportPaths ).'.xml' );
		$reportPaths[] = $path;
		return \array_merge( $args, [ '--log-junit', $path ] );
	}

	private function createReportDirectory( string $rootDir ) :string {
		$directory = Path::join( $rootDir, 'tmp', 'unit-test-reports-'.\bin2hex( \random_bytes( 8 ) ) );
		if ( !\mkdir( $directory, 0700, true ) && !\is_dir( $directory ) ) {
			throw new \RuntimeException( \sprintf( 'Unable to create unit test report directory: %s', $directory ) );
		}
		return $directory;
	}

	/** @param string[] $reportPaths */
	private function countJUnitTests( array $reportPaths ) :int {
		$count = 0;
		foreach ( $reportPaths as $path ) {
			$count += (int)( $this->junitReportTotals( $path )[ 'tests' ] ?? 0 );
		}
		return $count;
	}

	/** @param string[] $reportPaths */
	private function mergeJUnitReports( array $reportPaths, string $targetPath ) :void {
		$merged = new \DOMDocument( '1.0', 'UTF-8' );
		$merged->formatOutput = true;
		$root = $merged->appendChild( $merged->createElement( 'testsuites' ) );
		$totals = [ 'tests' => 0, 'assertions' => 0, 'errors' => 0, 'failures' => 0, 'skipped' => 0, 'time' => 0.0 ];
		foreach ( $reportPaths as $path ) {
			$document = new \DOMDocument();
			if ( !\is_file( $path ) || !@$document->load( $path ) || $document->documentElement === null ) {
				throw new \RuntimeException( \sprintf( 'Expected JUnit report was not produced: %s', $path ) );
			}
			foreach ( $this->junitReportTotals( $path ) as $attribute => $value ) {
				$totals[ $attribute ] += $value;
			}
			foreach ( $document->getElementsByTagName( 'testsuite' ) as $suite ) {
				if ( $suite->parentNode === $document->documentElement ) {
					$root->appendChild( $merged->importNode( $suite, true ) );
				}
			}
		}
		foreach ( $totals as $attribute => $value ) {
			$root->setAttribute( $attribute, $attribute === 'time' ? \number_format( $value, 6, '.', '' ) : (string)(int)$value );
		}
		if ( $merged->save( $targetPath ) === false ) {
			throw new \RuntimeException( \sprintf( 'Unable to write merged JUnit report: %s', $targetPath ) );
		}
	}

	/** @return array{tests:int,assertions:int,errors:int,failures:int,skipped:int,time:float} */
	private function junitReportTotals( string $path ) :array {
		$totals = [ 'tests' => 0, 'assertions' => 0, 'errors' => 0, 'failures' => 0, 'skipped' => 0, 'time' => 0.0 ];
		$document = new \DOMDocument();
		if ( !\is_file( $path ) || !@$document->load( $path ) || $document->documentElement === null ) {
			return $totals;
		}
		$root = $document->documentElement;
		if ( $root->nodeName === 'testsuite' ) {
			foreach ( \array_keys( $totals ) as $attribute ) {
				$value = (float)$root->getAttribute( $attribute );
				$totals[ $attribute ] = $attribute === 'time' ? $value : (int)$value;
			}
			return $totals;
		}
		foreach ( $root->childNodes as $child ) {
			if ( $child instanceof \DOMElement && $child->nodeName === 'testsuite' ) {
				foreach ( \array_keys( $totals ) as $attribute ) {
					$value = (float)$child->getAttribute( $attribute );
					$totals[ $attribute ] += $attribute === 'time' ? $value : (int)$value;
				}
			}
		}
		return $totals;
	}

	/** @param string[] $reportPaths */
	private function removeReportDirectory( ?string $reportDirectory, array $reportPaths ) :void {
		if ( $reportDirectory === null ) {
			return;
		}
		foreach ( $reportPaths as $path ) {
			if ( \is_file( $path ) ) {
				\unlink( $path );
			}
		}
		if ( \is_dir( $reportDirectory ) ) {
			\rmdir( $reportDirectory );
		}
	}

	/**
	 * ReleaseOperatorCommandTest cannot use the Brain Monkey unit bootstrap, so it
	 * is deliberately executed with Composer's autoloader only. Keep it in the
	 * public unit runner for whole-suite and matching selected runs.
	 *
	 * @param string[] $args
	 * @return string[]
	 */
	private function buildOperatorCommand( array $args, string $rootDir ) :array {
		return \array_merge( $this->selector->buildPhpCommand(), [
			'./vendor/phpunit/phpunit/phpunit',
			'--no-configuration',
			'--disallow-test-output',
			'--bootstrap',
			'vendor/autoload.php',
		], $this->operatorForwardArgs( $args, $rootDir ), [
			'tests/Unit/ReleaseOperatorCommandTest.php',
		] );
	}

	/** @param string[] $args */
	private function shouldRunOperatorSuite( array $args, string $rootDir ) :bool {
		if ( $args === [] ) {
			return true;
		}

		$pathIndexes = $this->concretePathArgumentIndexes( $args, $rootDir );
		if ( $pathIndexes !== [] ) {
			$operatorPath = Path::join( $rootDir, 'tests/Unit/ReleaseOperatorCommandTest.php' );
			foreach ( $pathIndexes as $pathIndex ) {
				$path = Path::join( $rootDir, $args[ $pathIndex ] );
				if ( \is_file( $path ) && \realpath( $path ) === \realpath( $operatorPath ) ) {
					return true;
				}
				if ( \is_dir( $path ) && \str_starts_with(
					\str_replace( '\\', '/', $operatorPath ),
					\rtrim( \str_replace( '\\', '/', $path ), '/' ).'/'
				) ) {
					return true;
				}
			}
			return false;
		}

		// With no concrete path, this is a whole-suite or option/filter selection.
		// Let the isolated PHPUnit process apply the caller's own selection instead
		// of attempting to duplicate PHPUnit's filter semantics here.
		return true;
	}

	/** @param string[] $args */
	private function hasExplicitOperatorPath( array $args, string $rootDir ) :bool {
		$operatorPath = \realpath( Path::join( $rootDir, 'tests/Unit/ReleaseOperatorCommandTest.php' ) );
		foreach ( $this->concretePathArgumentIndexes( $args, $rootDir ) as $pathIndex ) {
			if ( \realpath( Path::join( $rootDir, $args[ $pathIndex ] ) ) === $operatorPath ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string[] $args
	 * @return string[]
	 */
	private function withoutExplicitOperatorPath( array $args, string $rootDir ) :array {
		$operatorPath = \realpath( Path::join( $rootDir, 'tests/Unit/ReleaseOperatorCommandTest.php' ) );
		return \array_values( \array_filter(
			$args,
			static fn( string $arg ) :bool => \realpath( Path::join( $rootDir, $arg ) ) !== $operatorPath
		) );
	}

	/**
	 * @param string[] $args
	 * @return string[]
	 */
	private function operatorForwardArgs( array $args, string $rootDir ) :array {
		$pathIndexes = $this->concretePathArgumentIndexes( $args, $rootDir );
		$forwardArgs = [];
		for ( $index = 0; $index < \count( $args ); $index++ ) {
			if ( \in_array( $index, $pathIndexes, true ) ) {
				continue;
			}
			if ( $args[ $index ] === '--functional' ) {
				continue;
			}
			if ( \in_array( $args[ $index ], [
				'--bootstrap',
				'-c',
				'--configuration',
				'--max-batch-size',
				'--passthru',
				'--passthru-php',
				'--processes',
				'--runner',
				'--tmp-dir',
				'-p',
			], true ) ) {
				$index++;
				continue;
			}
			if ( \str_starts_with( $args[ $index ], '--bootstrap=' )
				 || \str_starts_with( $args[ $index ], '--configuration=' )
				 || \str_starts_with( $args[ $index ], '--max-batch-size=' )
				 || \str_starts_with( $args[ $index ], '--passthru=' )
				 || \str_starts_with( $args[ $index ], '--passthru-php=' )
				 || \str_starts_with( $args[ $index ], '--processes=' )
				 || \str_starts_with( $args[ $index ], '--runner=' )
				 || \str_starts_with( $args[ $index ], '--tmp-dir=' )
				 || \preg_match( '/^-p\\d+$/', $args[ $index ] ) === 1
			) {
				continue;
			}
			$forwardArgs[] = $args[ $index ];
		}
		return $forwardArgs;
	}


	/** @param string[] $args */
	private function buildCommand( array $args, string $mode, string $rootDir ) :array {
		$pathIndexes = $this->concretePathArgumentIndexes( $args, $rootDir );
		if ( $this->selector->selectStrategy( $args, $mode ) === UnitTestExecutionSelector::STRATEGY_PARATEST_WRAPPER
			 && \count( $pathIndexes ) === 1
			 && \is_file( Path::join( $rootDir, $args[ $pathIndexes[ 0 ] ] ) ) ) {
			foreach ( $args as $arg ) {
				if ( $arg === '--processes' || \strpos( $arg, '--processes=' ) === 0 || \strpos( $arg, '-p' ) === 0 ) {
					return $this->selector->buildCommand( $args, $mode );
				}
			}

			// WrapperRunner schedules whole files. Extra idle workers add no parallelism
			// and can stall during shutdown on Windows, including in the pre-commit hook.
			$args = \array_merge( [ '--processes=1' ], $args );
		}
		return $this->selector->buildCommand( $args, $mode );
	}

	/**
	 * Paratest accepts a single positional path. When callers provide multiple
	 * concrete test paths, preserve any options and run once per path.
	 *
	 * @param string[] $args
	 * @return array<int,string[]>|null
	 */
	private function splitParatestConcretePathRuns( array $args, string $mode, string $rootDir ) :?array {
		$strategy = $this->selector->selectStrategy( $args, $mode );
		if ( !$this->selector->isParatestStrategy( $strategy ) ) {
			return null;
		}

		$pathIndexes = $this->concretePathArgumentIndexes( $args, $rootDir );
		if ( \count( $pathIndexes ) < 2 ) {
			return null;
		}

		$splitArgs = [];
		foreach ( $pathIndexes as $pathIndex ) {
			$runArgs = [];
			foreach ( $args as $index => $arg ) {
				if ( \in_array( $index, $pathIndexes, true ) && $index !== $pathIndex ) {
					continue;
				}
				$runArgs[] = $arg;
			}
			$splitArgs[] = $runArgs;
		}

		return $splitArgs;
	}

	/**
	 * @param string[] $args
	 * @return int[]
	 */
	private function concretePathArgumentIndexes( array $args, string $rootDir ) :array {
		$pathIndexes = [];
		for ( $index = 0; $index < \count( $args ); $index++ ) {
			$arg = $args[ $index ];
			if ( $arg === '--' ) {
				continue;
			}

			if ( $this->isOptionWithInlineValue( $arg ) || $this->isFlagOption( $arg ) ) {
				continue;
			}

			if ( $this->isOptionWithSeparateValue( $arg ) ) {
				$index++;
				continue;
			}

			if ( !\file_exists( Path::join( $rootDir, $arg ) ) ) {
				return [];
			}

			$pathIndexes[] = $index;
		}

		return $pathIndexes;
	}

	private function isOptionWithInlineValue( string $arg ) :bool {
		return \strpos( $arg, '--' ) === 0 && \strpos( $arg, '=' ) !== false;
	}

	private function isFlagOption( string $arg ) :bool {
		return \strpos( $arg, '-' ) === 0 && !$this->isOptionWithSeparateValue( $arg );
	}

	private function isOptionWithSeparateValue( string $arg ) :bool {
		return \in_array(
			$arg,
			[
				'--bootstrap',
				'--colors',
				'-c',
				'--configuration',
				'--coverage-clover',
				'--coverage-cobertura',
				'--coverage-crap4j',
				'--coverage-html',
				'--coverage-php',
				'--coverage-test-limit',
				'--coverage-text',
				'--coverage-xml',
				'--exclude-group',
				'--filter',
				'-g',
				'--group',
				'--log-junit',
				'--log-teamcity',
				'-m',
				'--max-batch-size',
				'--order-by',
				'--passthru',
				'--passthru-php',
				'--path',
				'-p',
				'--processes',
				'--random-order-seed',
				'--repeat',
				'--runner',
				'--testsuite',
				'--tmp-dir',
				'--whitelist',
			],
			true
		);
	}

	/**
	 * @param string[] $args
	 * @return array{0:string,1:string[]}
	 */
	private function extractModeAndArgs( array $args ) :array {
		$mode = UnitTestExecutionSelector::MODE_AUTO;
		$forwardArgs = [];

		for ( $index = 0; $index < \count( $args ); $index++ ) {
			$arg = $args[ $index ];
			if ( !\is_string( $arg ) ) {
				continue;
			}

			if ( $arg === '--runner-mode' ) {
				$nextIndex = $index + 1;
				if ( !isset( $args[ $nextIndex ] ) || !\is_string( $args[ $nextIndex ] ) || $args[ $nextIndex ] === '' ) {
					throw new \InvalidArgumentException( 'Missing value for --runner-mode. Expected one of: auto, parallel, serial' );
				}
				$mode = $args[ $nextIndex ];
				$index++;
				continue;
			}

			if ( \strpos( $arg, '--runner-mode=' ) === 0 ) {
				$mode = (string)\substr( $arg, 14 );
				if ( $mode === '' ) {
					throw new \InvalidArgumentException( 'Missing value for --runner-mode. Expected one of: auto, parallel, serial' );
				}
				continue;
			}

			$forwardArgs[] = $arg;
		}

		$this->selector->assertValidMode( $mode );
		return [ $mode, $forwardArgs ];
	}
}
