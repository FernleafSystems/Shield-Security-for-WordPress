#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

require dirname( __DIR__ ).'/vendor/autoload.php';

const SHIELD_STATIC_ANALYSIS_MODES = [
	'--source' => 'analyze:source',
	'--package' => 'analyze:package',
];

$rootDir = Path::normalize( dirname( __DIR__ ) );
$args = \array_slice( $_SERVER[ 'argv' ] ?? [], 1 );
$parseResult = parseArgs( $args );

if ( $parseResult[ 'help' ] === true ) {
	writeHelp();
	exit( 0 );
}

if ( $parseResult[ 'error' ] !== null ) {
	\fwrite( \STDERR, 'Error: '.$parseResult[ 'error' ].\PHP_EOL );
	\fwrite( \STDERR, 'Use --help for usage.'.\PHP_EOL );
	exit( 1 );
}

$command = $parseResult[ 'command' ] ?? 'analyze:source';
$processRunner = new ProcessRunner();

try {
	$process = $processRunner->run(
		[
			\PHP_BINARY,
			'./bin/shield',
			$command,
		],
		$rootDir
	);
	exit( $process->getExitCode() ?? 1 );
}
catch ( \Throwable $throwable ) {
	\fwrite( \STDERR, 'Error: '.$throwable->getMessage().\PHP_EOL );
	exit( 1 );
}

/**
 * @return array{help:bool,error:?string,command:?string}
 */
function parseArgs( array $args ) :array {
	$wantsHelp = false;
	$selectedCommand = null;

	foreach ( $args as $arg ) {
		if ( $arg === '--help' || $arg === '-h' ) {
			$wantsHelp = true;
			continue;
		}

		if ( !isset( SHIELD_STATIC_ANALYSIS_MODES[ $arg ] ) ) {
			return [
				'help' => false,
				'error' => 'Unknown argument: '.$arg,
				'command' => null,
			];
		}

		$command = SHIELD_STATIC_ANALYSIS_MODES[ $arg ];
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

function writeHelp() :void {
	\fwrite( \STDOUT, 'Usage: php bin/run-static-analysis.php [--source|--package]'.\PHP_EOL );
	\fwrite( \STDOUT, \PHP_EOL );
	\fwrite( \STDOUT, 'Modes:'.\PHP_EOL );
	\fwrite( \STDOUT, '  (default) Source static analysis checks'.\PHP_EOL );
	\fwrite( \STDOUT, '  --source  Source static analysis checks'.\PHP_EOL );
	\fwrite( \STDOUT, '  --package Packaged static analysis checks'.\PHP_EOL );
	\fwrite( \STDOUT, \PHP_EOL );
	\fwrite( \STDOUT, 'Primary CLI: php bin/shield <command>'.\PHP_EOL );
	\fwrite( \STDOUT, 'This script is a backwards-compatible adapter.'.\PHP_EOL );
}
