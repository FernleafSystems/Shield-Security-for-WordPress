#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\Process\BashCommandResolver;
use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

require dirname( __DIR__ ).'/vendor/autoload.php';

const SHIELD_STATIC_ANALYSIS_MODES = [
	'--source' => 'source',
	'--package' => 'package',
];

$rootDir = Path::normalize( dirname( __DIR__ ) );
$dockerTestsScriptRelative = './'.Path::join( 'bin', 'run-docker-tests.sh' );
$dockerTestsScript = Path::join( $rootDir, $dockerTestsScriptRelative );
$buildConfigScript = Path::join( $rootDir, 'bin', 'build-config.php' );
$phpStanBinary = Path::join( $rootDir, 'vendor', 'phpstan', 'phpstan', 'phpstan' );
$phpStanConfig = Path::join( $rootDir, 'phpstan.neon.dist' );
$args = array_slice( $_SERVER['argv'] ?? [], 1 );
$parseResult = parseArgs( $args );
$processRunner = new ProcessRunner();
$bashCommandResolver = new BashCommandResolver();

$run = static function ( array $command, string $workingDir ) use ( $processRunner ) :int {
	return $processRunner->run( $command, $workingDir )->getExitCode() ?? 1;
};

if ( $parseResult[ 'help' ] === true ) {
	writeHelp();
	exit( 0 );
}

if ( $parseResult[ 'error' ] !== null ) {
	fwrite( STDERR, 'Error: '.$parseResult[ 'error' ].PHP_EOL );
	fwrite( STDERR, 'Use --help for usage.'.PHP_EOL );
	exit( 1 );
}
$mode = $parseResult[ 'mode' ] ?? 'source';

if ( $mode === 'package' ) {
	if ( !is_file( $dockerTestsScript ) ) {
		fwrite( STDERR, 'Package analysis script not found: '.$dockerTestsScript.PHP_EOL );
		exit( 1 );
	}

	exit(
		$run(
			[
				$bashCommandResolver->resolve(),
				$dockerTestsScriptRelative,
				'--analyze-package',
			],
			$rootDir
		)
	);
}

// Source-only static analysis runner. Packaged analysis is executed through Docker.
$buildCode = $run(
	[ PHP_BINARY, $buildConfigScript ],
	$rootDir
);

if ( $buildCode !== 0 ) {
	exit( $buildCode );
}

exit(
	$run(
		[
			PHP_BINARY,
			$phpStanBinary,
			'analyse',
			'-c',
			$phpStanConfig,
			'--no-progress',
			'--memory-limit=1G',
		],
		$rootDir
	)
);

/**
 * @return array{help:bool,error:?string,mode:?string}
 */
function parseArgs( array $args ) :array {
	$wantsHelp = false;
	$selectedMode = null;

	foreach ( $args as $arg ) {
		if ( $arg === '--help' || $arg === '-h' ) {
			$wantsHelp = true;
			continue;
		}

		if ( !isset( SHIELD_STATIC_ANALYSIS_MODES[ $arg ] ) ) {
			return [
				'help' => false,
				'error' => 'Unknown argument: '.$arg,
				'mode' => null,
			];
		}

		$mode = SHIELD_STATIC_ANALYSIS_MODES[ $arg ];
		if ( $selectedMode !== null && $selectedMode !== $mode ) {
			return [
				'help' => false,
				'error' => 'Only one mode flag can be provided at a time.',
				'mode' => null,
			];
		}
		$selectedMode = $mode;
	}

	if ( $wantsHelp ) {
		return [
			'help' => true,
			'error' => null,
			'mode' => null,
		];
	}

	return [
		'help' => false,
		'error' => null,
		'mode' => $selectedMode,
	];
}

function writeHelp() :void {
	fwrite( STDOUT, 'Usage: php bin/run-static-analysis.php [--source|--package]'.PHP_EOL );
	fwrite( STDOUT, PHP_EOL );
	fwrite( STDOUT, 'Modes:'.PHP_EOL );
	fwrite( STDOUT, '  (default) Source static analysis (build config + phpstan)'.PHP_EOL );
	fwrite( STDOUT, '  --source  Source static analysis (build config + phpstan)'.PHP_EOL );
	fwrite( STDOUT, '  --package Packaged static analysis via ./bin/run-docker-tests.sh --analyze-package'.PHP_EOL );
}
