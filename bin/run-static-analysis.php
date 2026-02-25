#!/usr/bin/env php
<?php declare( strict_types=1 );

use Symfony\Component\Process\Process;

require dirname( __DIR__ ).'/vendor/autoload.php';

$rootDir = dirname( __DIR__ );
$packageDir = $rootDir.'/shield-package';

/**
 * Remove local package artefacts created in the repository root.
 */
$cleanupPackageDir = static function () use ( $packageDir ) :void {
	if ( !is_dir( $packageDir ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $packageDir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		$itemPath = $item->getPathname();
		if ( $item->isDir() ) {
			@rmdir( $itemPath );
		}
		else {
			@unlink( $itemPath );
		}
	}

	@rmdir( $packageDir );
};

$run = static function ( array $command, string $workingDir ) :int {
	$process = new Process( $command, $workingDir );
	$process->setTimeout( null );
	$process->run(
		static function ( string $type, string $buffer ) :void {
			echo $buffer;
		}
	);
	return $process->getExitCode() ?? 1;
};

$cleanupPackageDir();

$exitCode = 1;
try {
	$buildCode = $run(
		[ PHP_BINARY, $rootDir.'/bin/build-config.php' ],
		$rootDir
	);
	if ( $buildCode !== 0 ) {
		$exitCode = $buildCode;
	}
	else {
		$exitCode = $run(
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
		);
	}
}
finally {
	$cleanupPackageDir();
}

exit( $exitCode );
