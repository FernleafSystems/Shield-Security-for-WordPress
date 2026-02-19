#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\StaticAnalysis\PackagedPhpStanAnalysisOrchestrator;

require dirname( __DIR__ ).'/vendor/autoload.php';

$options = \getopt( '', [
	'project-root:',
	'composer-image:',
	'package-dir:',
	'package-dir-relative:',
	'help',
] );

if ( isset( $options[ 'help' ] ) ) {
	\fwrite(
		\STDOUT,
		"Usage: php bin/run-packaged-phpstan.php --project-root=<path> --composer-image=<image> --package-dir=<path> --package-dir-relative=<path>".PHP_EOL
	);
	exit( 0 );
}

$projectRoot = normalizePathOption( (string)( $options[ 'project-root' ] ?? '' ) );
$composerImage = \trim( (string)( $options[ 'composer-image' ] ?? '' ) );
$packageDir = normalizePathOption( (string)( $options[ 'package-dir' ] ?? '' ) );
$packageDirRelative = \trim( (string)( $options[ 'package-dir-relative' ] ?? '' ) );

if ( $projectRoot === '' || $composerImage === '' || $packageDir === '' || $packageDirRelative === '' ) {
	\fwrite(
		\STDERR,
		"Usage: php bin/run-packaged-phpstan.php --project-root=<path> --composer-image=<image> --package-dir=<path> --package-dir-relative=<path>".PHP_EOL
	);
	exit( 1 );
}

$configPath = $projectRoot.'/phpstan.package.neon.dist';
$bootstrapPath = $projectRoot.'/tests/stubs/phpstan-package-bootstrap.php';
$packageVendorAutoload = $packageDir.'/vendor/autoload.php';
$packagePrefixedAutoload = $packageDir.'/vendor_prefixed/autoload.php';

if ( !\is_file( $configPath ) ) {
	\fwrite( \STDERR, "ERROR: Missing phpstan.package.neon.dist at project root".PHP_EOL );
	exit( 1 );
}

if ( !\is_file( $bootstrapPath ) ) {
	\fwrite( \STDERR, "ERROR: Missing tests/stubs/phpstan-package-bootstrap.php".PHP_EOL );
	exit( 1 );
}

if ( !\is_file( $packageVendorAutoload ) ) {
	\fwrite( \STDERR, "ERROR: Packaged vendor autoload not found: ".$packageVendorAutoload.PHP_EOL );
	exit( 1 );
}

if ( !\is_file( $packagePrefixedAutoload ) ) {
	\fwrite( \STDERR, "ERROR: Packaged vendor_prefixed autoload not found: ".$packagePrefixedAutoload.PHP_EOL );
	exit( 1 );
}

$packageContainerPath = '/app/'.\trim( \str_replace( '\\', '/', $packageDirRelative ), '/' );
echo "Running PHPStan against packaged plugin...".PHP_EOL;
echo "   Using config: /app/phpstan.package.neon.dist".PHP_EOL;
echo "   Using package path: ".$packageContainerPath.PHP_EOL;

try {
	$orchestrator = new PackagedPhpStanAnalysisOrchestrator();
	$outcome = $orchestrator->runCommand(
		$orchestrator->buildDockerCommand( $projectRoot, $composerImage, $packageDirRelative ),
		$projectRoot
	);

	echo $outcome->toConsoleMessage().PHP_EOL;
	exit( $outcome->toExitCode() );
}
catch ( \Throwable $throwable ) {
	\fwrite( \STDERR, "ERROR: Packaged PHPStan execution failed: ".$throwable->getMessage().PHP_EOL );
	exit( 1 );
}

function normalizePathOption( string $value ) :string {
	$value = \trim( $value, " \t\n\r\0\x0B\"'" );
	return $value === '' ? '' : \str_replace( '\\', '/', $value );
}
