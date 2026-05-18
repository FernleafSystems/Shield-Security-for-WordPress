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

		$splitArgs = $this->splitParatestConcretePathRuns( $forwardArgs, $mode, $rootDir );
		if ( $splitArgs !== null ) {
			foreach ( $splitArgs as $runArgs ) {
				$exitCode = $this->processRunner->runForExitCode(
					$this->selector->buildCommand( $runArgs, $mode ),
					$rootDir
				);
				if ( $exitCode !== 0 ) {
					return $exitCode;
				}
			}
			return 0;
		}

		return $this->processRunner->runForExitCode(
			$this->selector->buildCommand( $forwardArgs, $mode ),
			$rootDir
		);
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
