#!/usr/bin/env php
<?php declare( strict_types=1 );

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
	echo "ERROR: Packaged PHPStan output could not be parsed as JSON (infrastructure/config failure).".PHP_EOL;
	exit( 1 );
}

$outcome = ( new PackagedPhpStanOutcomeClassifier() )->classify( $phpstanExitCode, $content );

if ( $outcome === PackagedPhpStanOutcomeClassifier::OUTCOME_CLEAN_SUCCESS ) {
	echo "✅ Packaged PHPStan analysis completed with no findings.".PHP_EOL;
	exit( 0 );
}

if ( $outcome === PackagedPhpStanOutcomeClassifier::OUTCOME_FINDINGS_SUCCESS ) {
	echo "⚠️  Packaged PHPStan completed with findings (informational only).".PHP_EOL;
	exit( 0 );
}

if ( $outcome === PackagedPhpStanOutcomeClassifier::OUTCOME_NON_REPORTABLE_FAILURE ) {
	echo "ERROR: Packaged PHPStan returned non-zero without reportable findings.".PHP_EOL;
	exit( 1 );
}

echo "ERROR: Packaged PHPStan output could not be parsed as JSON (infrastructure/config failure).".PHP_EOL;
exit( 1 );

