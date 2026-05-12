#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanOutcome;
use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanOutcomeClassifier;

require dirname( __DIR__ ).'/vendor/autoload.php';

$args = array_slice( $_SERVER['argv'] ?? [], 1 );

if ( \count( $args ) < 2 ) {
	\fwrite( \STDERR, "Usage: php bin/classify-packaged-phpstan.php <output-file> <phpstan-exit-code>".PHP_EOL );
	exit( 1 );
}

$outputPath = (string)$args[ 0 ];
$phpstanExitCode = (int)$args[ 1 ];
$content = @\file_get_contents( $outputPath );

if ( $content === false ) {
	$outcome = PackagedPhpStanOutcome::parseFailure();
	echo $outcome->toConsoleMessage().PHP_EOL;
	exit( $outcome->toExitCode() );
}

$outcome = ( new PackagedPhpStanOutcomeClassifier() )->classify( $phpstanExitCode, $content );
echo $outcome->toConsoleMessage().PHP_EOL;
exit( $outcome->toExitCode() );
