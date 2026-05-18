<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Process\Process;

class DockerComposeExecutor {

	private ProcessRunner $processRunner;

	public function __construct( ?ProcessRunner $processRunner = null ) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
	}

	/**
	 * @param string[] $composeFiles
	 * @param string[] $subCommand
	 * @param array<string,string|false>|null $envOverrides
	 * @param callable|null $onOutput
	 * @param bool $showDockerOutput
	 */
	public function run(
		string $rootDir,
		array $composeFiles,
		array $subCommand,
		?array $envOverrides = null,
		?callable $onOutput = null,
		bool $showDockerOutput = true
	) :int {
		return $this->processRunner->run(
			$this->buildCommand( $composeFiles, $subCommand, $showDockerOutput ),
			$rootDir,
			$onOutput,
			$envOverrides
		)->getExitCode() ?? 1;
	}

	/**
	 * @param string[] $composeFiles
	 * @param string[] $subCommand
	 * @param array<string,string|false>|null $envOverrides
	 * @param bool $showDockerOutput
	 */
	public function runIgnoringFailure(
		string $rootDir,
		array $composeFiles,
		array $subCommand,
		?array $envOverrides = null,
		bool $showDockerOutput = true
	) :void {
		$this->processRunner->run(
			$this->buildCommand( $composeFiles, $subCommand, $showDockerOutput ),
			$rootDir,
			$showDockerOutput
				? static function ( string $type, string $buffer ) :void {
					if ( $type === Process::ERR ) {
						\fwrite( \STDERR, $buffer );
					}
					else {
						echo $buffer;
					}
				}
				: static function () :void {},
			$envOverrides
		);
	}

	/**
	 * @param string[] $composeFiles
	 * @param string[] $subCommand
	 * @param bool $showDockerOutput
	 * @return string[]
	 */
	private function buildCommand( array $composeFiles, array $subCommand, bool $showDockerOutput ) :array {
		$command = [ 'docker', 'compose' ];
		foreach ( $composeFiles as $composeFile ) {
			$command[] = '-f';
			$command[] = $composeFile;
		}
		return \array_merge( $command, $this->adjustComposeCommandForOutput( $subCommand, $showDockerOutput ) );
	}

	/**
	 * @param string[] $subCommand
	 * @return string[]
	 */
	private function adjustComposeCommandForOutput( array $subCommand, bool $showDockerOutput ) :array {
		if ( $showDockerOutput || $subCommand === [] ) {
			return $subCommand;
		}

		$action = \array_shift( $subCommand );
		if ( \count( $subCommand ) === 0 ) {
			return [ $action ];
		}

		switch ( $action ) {
			case 'up':
				return \array_merge( [ $action, '--quiet-pull' ], $subCommand );
			case 'build':
				return \array_merge( [ $action, '--quiet' ], $subCommand );
			case 'run':
				return \array_merge( [ $action, '--quiet-pull' ], $subCommand );
		}

		return \array_merge( [ $action ], $subCommand );
	}
}
