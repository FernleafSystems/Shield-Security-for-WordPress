#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\Testing\UnitTestScriptRunner;
use Symfony\Component\Filesystem\Path;

require dirname( __DIR__ ).'/vendor/autoload.php';

$rootDir = Path::normalize( dirname( __DIR__ ) );
$args = \array_slice( $_SERVER[ 'argv' ] ?? [], 1 );

try {
	exit(
		( new UnitTestScriptRunner() )->run( $args, $rootDir )
	);
}
catch ( \Throwable $throwable ) {
	\fwrite( \STDERR, 'Error: '.$throwable->getMessage().\PHP_EOL );
	exit( 1 );
}
