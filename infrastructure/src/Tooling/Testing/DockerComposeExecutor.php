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
	 */
	public function run( string $rootDir, array $composeFiles, array $subCommand, ?array $envOverrides = null ) :int {
		return $this->processRunner->run(
			$this->buildCommand( $composeFiles, $subCommand ),
			$rootDir,
			null,
			$envOverrides
		)->getExitCode() ?? 1;
	}

	/**
	 * @param string[] $composeFiles
	 * @param string[] $subCommand
	 * @param array<string,string|false>|null $envOverrides
	 */
	public function runIgnoringFailure(
		string $rootDir,
		array $composeFiles,
		array $subCommand,
		?array $envOverrides = null
	) :void {
		$this->processRunner->run(
			$this->buildCommand( $composeFiles, $subCommand ),
			$rootDir,
			static function ( string $type, string $buffer ) :void {
				if ( $type === Process::ERR ) {
					\fwrite( \STDERR, $buffer );
				}
				else {
					echo $buffer;
				}
			},
			$envOverrides
		);
	}

	/**
	 * @param string[] $composeFiles
	 * @param string[] $subCommand
	 * @return string[]
	 */
	private function buildCommand( array $composeFiles, array $subCommand ) :array {
		$command = [ 'docker', 'compose' ];
		foreach ( $composeFiles as $composeFile ) {
			$command[] = '-f';
			$command[] = $composeFile;
		}
		return \array_merge( $command, $subCommand );
	}
}
