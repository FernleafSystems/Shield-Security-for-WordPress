#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanAnalysisOrchestrator;

require dirname( __DIR__ ).'/vendor/autoload.php';

const RUN_PACKAGED_PHPSTAN_USAGE = 'Usage: php bin/run-packaged-phpstan.php --project-root=<path> --composer-image=<image> --package-dir=<path> --package-dir-relative=<path>';

$options = \getopt( '', [
	'project-root:',
	'composer-image:',
	'package-dir:',
	'package-dir-relative:',
	'help',
] );

if ( isset( $options[ 'help' ] ) ) {
	writeUsage( \STDOUT );
	exit( 0 );
}

$projectRoot = normalizePathOption( (string)( $options[ 'project-root' ] ?? '' ) );
$composerImage = \trim( (string)( $options[ 'composer-image' ] ?? '' ) );
$packageDir = normalizePathOption( (string)( $options[ 'package-dir' ] ?? '' ) );
$packageDirRelative = \trim( (string)( $options[ 'package-dir-relative' ] ?? '' ) );

if ( $projectRoot === '' || $composerImage === '' || $packageDir === '' || $packageDirRelative === '' ) {
	writeUsage( \STDERR );
	exit( 1 );
}

$orchestrator = new PackagedPhpStanAnalysisOrchestrator();
$packageContainerPath = $orchestrator->buildPackageContainerPath( $packageDirRelative );

try {
	$orchestrator->assertPreflight( $projectRoot, $packageDir );

	echo "Running PHPStan against packaged plugin...".PHP_EOL;
	echo "   Using config: /app/phpstan.package.neon.dist".PHP_EOL;
	echo "   Using package path: ".$packageContainerPath.PHP_EOL;

	$outcome = $orchestrator->runCommand(
		$orchestrator->buildDockerCommand( $projectRoot, $composerImage, $packageDirRelative ),
		$projectRoot
	);

	echo $outcome->toConsoleMessage().PHP_EOL;
	exit( $outcome->toExitCode() );
}
catch ( \Throwable $throwable ) {
	$message = $throwable->getMessage();
	if ( \strpos( $message, 'ERROR:' ) !== 0 ) {
		$message = 'ERROR: Packaged PHPStan execution failed: '.$message;
	}
	\fwrite( \STDERR, $message.PHP_EOL );
	exit( 1 );
}

function normalizePathOption( string $value ) :string {
	$value = \trim( $value, " \t\n\r\0\x0B\"'" );
	return $value === '' ? '' : \str_replace( '\\', '/', $value );
}

/**
 * @param resource $stream
 */
function writeUsage( $stream ) :void {
	\fwrite( $stream, RUN_PACKAGED_PHPSTAN_USAGE.\PHP_EOL );
}
