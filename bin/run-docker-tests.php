#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

require dirname( __DIR__ ).'/vendor/autoload.php';

const SHIELD_DOCKER_TEST_MODES = [
	'--source' => 'test:source',
	'--package-targeted' => 'test:package-targeted',
	'--package-full' => 'test:package-full',
	'--analyze-source' => 'analyze:source',
	'--analyze-package' => 'analyze:package',
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

$command = $parseResult[ 'command' ] ?? 'test:source';
$runner = new ProcessRunner();

try {
	$process = $runner->run(
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

		if ( !isset( SHIELD_DOCKER_TEST_MODES[ $arg ] ) ) {
			return [
				'help' => false,
				'error' => 'Unknown argument: '.$arg,
				'command' => null,
			];
		}

		$command = SHIELD_DOCKER_TEST_MODES[ $arg ];
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
	\fwrite( \STDOUT, 'Usage: php bin/run-docker-tests.php [--source|--package-targeted|--package-full|--analyze-source|--analyze-package]'.\PHP_EOL );
	\fwrite( \STDOUT, \PHP_EOL );
	\fwrite( \STDOUT, 'Modes:'.\PHP_EOL );
	\fwrite( \STDOUT, '  (default)         Source runtime checks against working tree'.\PHP_EOL );
	\fwrite( \STDOUT, '  --source          Source runtime checks against working tree'.\PHP_EOL );
	\fwrite( \STDOUT, '  --package-targeted Focused package validation checks'.\PHP_EOL );
	\fwrite( \STDOUT, '  --package-full    Full packaged Docker runtime checks'.\PHP_EOL );
	\fwrite( \STDOUT, '  --analyze-source  Source static analysis checks'.\PHP_EOL );
	\fwrite( \STDOUT, '  --analyze-package Packaged static analysis checks'.\PHP_EOL );
	\fwrite( \STDOUT, \PHP_EOL );
	\fwrite( \STDOUT, 'Primary CLI: php bin/shield <command>'.\PHP_EOL );
	\fwrite( \STDOUT, 'This script is a backwards-compatible adapter.'.\PHP_EOL );
}
