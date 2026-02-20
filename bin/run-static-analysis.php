#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

require dirname( __DIR__ ).'/vendor/autoload.php';

$rootDir = Path::normalize( dirname( __DIR__ ) );
$dockerTestsScriptRelative = './'.Path::join( 'bin', 'run-docker-tests.sh' );
$dockerTestsScript = Path::join( $rootDir, $dockerTestsScriptRelative );
$buildConfigScript = Path::join( $rootDir, 'bin', 'build-config.php' );
$phpStanBinary = Path::join( $rootDir, 'vendor', 'phpstan', 'phpstan', 'phpstan' );
$phpStanConfig = Path::join( $rootDir, 'phpstan.neon.dist' );
$args = array_slice( $_SERVER['argv'] ?? [], 1 );
$processRunner = new ProcessRunner();

$run = static function ( array $command, string $workingDir ) use ( $processRunner ) :int {
	return $processRunner->run( $command, $workingDir )->getExitCode() ?? 1;
};

if ( in_array( '--help', $args, true ) || in_array( '-h', $args, true ) ) {
	fwrite( STDOUT, "Usage: php bin/run-static-analysis.php [--source|--package]".PHP_EOL );
	exit( 0 );
}

$mode = 'source';
if ( in_array( '--package', $args, true ) ) {
	$mode = 'package';
}
elseif ( in_array( '--source', $args, true ) ) {
	$mode = 'source';
}

if ( $mode === 'package' ) {
	if ( !is_file( $dockerTestsScript ) ) {
		fwrite( STDERR, 'Package analysis script not found: '.$dockerTestsScript.PHP_EOL );
		exit( 1 );
	}

	exit(
		$run(
			[
				resolveBashCommand(),
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

function resolveBashCommand() :string {
	if ( \PHP_OS_FAMILY !== 'Windows' ) {
		return 'bash';
	}

	$override = normalizeOptionalPath( (string)( \getenv( 'SHIELD_BASH_BINARY' ) ?: '' ) );
	if ( $override !== '' && \is_file( $override ) ) {
		return $override;
	}

	foreach ( [ 'ProgramW6432', 'ProgramFiles', 'ProgramFiles(x86)' ] as $envVar ) {
		$baseDir = normalizeOptionalPath( (string)( \getenv( $envVar ) ?: '' ) );
		if ( $baseDir === '' ) {
			continue;
		}

		$candidate = Path::join( $baseDir, 'Git', 'bin', 'bash.exe' );
		if ( \is_file( $candidate ) ) {
			return $candidate;
		}
	}

	return 'bash';
}

function normalizeOptionalPath( string $value ) :string {
	$trimmed = \trim( $value, " \t\n\r\0\x0B\"'" );
	return $trimmed === '' ? '' : Path::normalize( $trimmed );
}
