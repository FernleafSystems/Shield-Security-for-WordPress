#!/usr/bin/env php
<?php declare( strict_types=1 );

use Symfony\Component\Process\Process;

require dirname( __DIR__ ).'/vendor/autoload.php';

$rootDir = dirname( __DIR__ );
$args = array_slice( $_SERVER['argv'] ?? [], 1 );

$run = static function ( array $command, string $workingDir ) :int {
	$process = new Process( $command, $workingDir );
	$process->setTimeout( null );
	$process->run(
		static function ( string $type, string $buffer ) :void {
			$normalized = str_replace( [ "\r\n", "\r" ], "\n", $buffer );
			if ( PHP_EOL !== "\n" ) {
				$normalized = str_replace( "\n", PHP_EOL, $normalized );
			}
			if ( $type === Process::ERR ) {
				fwrite( STDERR, $normalized );
			}
			else {
				echo $normalized;
			}
		}
	);
	return $process->getExitCode() ?? 1;
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
	exit(
		$run(
			[
				'bash',
				$rootDir.'/bin/run-docker-tests.sh',
				'--analyze-package',
			],
			$rootDir
		)
	);
}

// Source-only static analysis runner. Packaged analysis is executed through Docker.
$buildCode = $run(
	[ PHP_BINARY, $rootDir.'/bin/build-config.php' ],
	$rootDir
);

if ( $buildCode !== 0 ) {
	exit( $buildCode );
}

exit(
	$run(
		[
			PHP_BINARY,
			$rootDir.'/vendor/phpstan/phpstan/phpstan',
			'analyse',
			'-c',
			$rootDir.'/phpstan.neon.dist',
			'--no-progress',
			'--memory-limit=1G',
		],
		$rootDir
	)
);
