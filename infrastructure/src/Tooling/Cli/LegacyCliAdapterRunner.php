<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;

class LegacyCliAdapterRunner {

	private ProcessRunner $processRunner;

	public function __construct( ?ProcessRunner $processRunner = null ) {
		$this->processRunner = $processRunner ?? new ProcessRunner();
	}

	/**
	 * @param string[] $args
	 * @param array<string,string> $modeMap
	 */
	public function run(
		array $args,
		string $rootDir,
		array $modeMap,
		string $defaultCommand,
		callable $writeHelp
	) :int {
		$parseResult = $this->parseArgs( $args, $modeMap );

		if ( $parseResult[ 'help' ] === true ) {
			$writeHelp();
			return 0;
		}

		if ( $parseResult[ 'error' ] !== null ) {
			\fwrite( \STDERR, 'Error: '.$parseResult[ 'error' ].\PHP_EOL );
			\fwrite( \STDERR, 'Use --help for usage.'.\PHP_EOL );
			return 1;
		}

		$command = $parseResult[ 'command' ] ?? $defaultCommand;
		try {
			$process = $this->processRunner->run(
				[
					\PHP_BINARY,
					'./bin/shield',
					$command,
				],
				$rootDir
			);
			return $process->getExitCode() ?? 1;
		}
		catch ( \Throwable $throwable ) {
			\fwrite( \STDERR, 'Error: '.$throwable->getMessage().\PHP_EOL );
			return 1;
		}
	}

	/**
	 * @param string[] $args
	 * @param array<string,string> $modeMap
	 * @return array{help:bool,error:?string,command:?string}
	 */
	private function parseArgs( array $args, array $modeMap ) :array {
		$wantsHelp = false;
		$selectedCommand = null;

		foreach ( $args as $arg ) {
			if ( $arg === '--help' || $arg === '-h' ) {
				$wantsHelp = true;
				continue;
			}

			if ( !isset( $modeMap[ $arg ] ) ) {
				return [
					'help' => false,
					'error' => 'Unknown argument: '.$arg,
					'command' => null,
				];
			}

			$command = $modeMap[ $arg ];
			if ( $selectedCommand !== null && $selectedCommand !== $command ) {
				return [
					'help' => false,
					'error' => 'Only one mode flag can be provided at a time.',
					'command' => null,
				];
			}
			$selectedCommand = $command;
		}

		if ( $wantsHelp ) {
			return [
				'help' => true,
				'error' => null,
				'command' => null,
			];
		}

		return [
			'help' => false,
			'error' => null,
			'command' => $selectedCommand,
		];
	}
}
