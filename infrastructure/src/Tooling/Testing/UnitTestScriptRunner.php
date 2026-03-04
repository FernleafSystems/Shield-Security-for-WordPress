<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;

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

		return $this->processRunner->runForExitCode(
			$this->selector->buildCommand( $forwardArgs, $mode ),
			$rootDir
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

