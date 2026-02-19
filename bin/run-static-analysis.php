#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;

require dirname( __DIR__ ).'/vendor/autoload.php';

$rootDir = dirname( __DIR__ );
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
