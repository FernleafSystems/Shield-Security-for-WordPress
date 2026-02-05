#!/usr/bin/env php
<?php
declare( strict_types=1 );

/**
 * Development Strauss Runner
 *
 * Runs Strauss with --deleteVendorPackages=false so vendor_prefixed/ is created
 * alongside vendor/, making AptowebDeps\* prefixed classes available for testing
 * without breaking the development environment.
 *
 * Usage:
 *   php bin/run-strauss-dev.php              # Run Strauss in dev mode
 *   php bin/run-strauss-dev.php --clean      # Remove vendor_prefixed/
 *   php bin/run-strauss-dev.php --strauss-version=0.26.3
 *   php bin/run-strauss-dev.php --strauss-fork-repo=https://github.com/user/strauss
 */

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\CommandRunner;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\SafeDirectoryRemover;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\StraussBinaryProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PackagerConfig;
use Symfony\Component\Filesystem\Path;

require dirname( __DIR__ ).'/vendor/autoload.php';

$options = getopt( '', [
	'clean',
	'strauss-version::',
	'strauss-fork-repo::',
	'help',
] );

$projectRoot = Path::normalize( dirname( __DIR__ ) );
$vendorPrefixedDir = Path::join( $projectRoot, 'vendor_prefixed' );

/**
 * Logger function for console output.
 */
$logger = static function ( string $message ) :void {
	echo $message.PHP_EOL;
};

// Show help
if ( isset( $options[ 'help' ] ) ) {
	echo <<<HELP
Development Strauss Runner

Runs Strauss with --deleteVendorPackages=false to create vendor_prefixed/
alongside vendor/, enabling prefixed class testing in dev environment.

Usage:
  php bin/run-strauss-dev.php [options]

Options:
  --clean                Remove vendor_prefixed/ directory
  --strauss-version      Specify Strauss version (e.g., 0.26.3)
  --strauss-fork-repo    Use a custom Strauss fork repository URL
  --help                 Show this help message

Examples:
  php bin/run-strauss-dev.php
  php bin/run-strauss-dev.php --clean
  php bin/run-strauss-dev.php --strauss-version=0.26.3
  php bin/run-strauss-dev.php --strauss-fork-repo=https://github.com/paulgoodchild/strauss

HELP;
	exit( 0 );
}

// Handle --clean option
if ( isset( $options[ 'clean' ] ) ) {
	$logger( 'Cleaning vendor_prefixed directory...' );

	if ( \is_dir( $vendorPrefixedDir ) ) {
		$directoryRemover = new SafeDirectoryRemover( $projectRoot );
		$directoryRemover->removeSubdirectoryOf( $vendorPrefixedDir, $projectRoot );
		$logger( '  Removed: '.$vendorPrefixedDir );
	}
	else {
		$logger( '  Directory does not exist: '.$vendorPrefixedDir );
	}

	exit( 0 );
}

// Verify vendor/ exists (composer install must have been run)
$vendorDir = Path::join( $projectRoot, 'vendor' );
if ( !\is_dir( $vendorDir ) ) {
	\fwrite( STDERR, 'Error: vendor/ directory not found. Run "composer install" first.'.PHP_EOL );
	exit( 1 );
}

// Resolve Strauss version: CLI arg > env var/config file > fallback
$straussVersion = $options[ 'strauss-version' ] ?? null;
$resolvedStrauss = null;
if ( \is_string( $straussVersion ) && $straussVersion !== '' ) {
	$resolvedStrauss = \ltrim( \trim( $straussVersion ), 'v' );
}
else {
	$resolvedStrauss = PackagerConfig::getStraussVersion();
}

// Use fallback if nothing specified
if ( $resolvedStrauss === null || $resolvedStrauss === '' ) {
	$resolvedStrauss = StraussBinaryProvider::getFallbackVersion();
}

// Resolve fork repo: CLI arg > env var/config file > null
$straussForkRepo = $options[ 'strauss-fork-repo' ] ?? null;
if ( !\is_string( $straussForkRepo ) || $straussForkRepo === '' ) {
	$straussForkRepo = PackagerConfig::getStraussForkRepo();
}

try {
	$logger( '=== Development Strauss Runner ===' );
	$logger( '' );

	// Warn if vendor_prefixed already exists
	if ( \is_dir( $vendorPrefixedDir ) ) {
		$logger( 'Note: vendor_prefixed/ already exists (will be overwritten)' );
		$logger( '' );
	}

	// Initialize components
	$commandRunner = new CommandRunner( $projectRoot, $logger );
	$directoryRemover = new SafeDirectoryRemover( $projectRoot );
	$straussProvider = new StraussBinaryProvider(
		$resolvedStrauss,
		$straussForkRepo,
		$commandRunner,
		$directoryRemover,
		$logger
	);

	// Get strauss binary (downloads PHAR or clones fork)
	$straussBinary = $straussProvider->provide( $projectRoot );

	$logger( 'Running Strauss with --deleteVendorPackages=false...' );
	$logger( '' );

	// Run Strauss WITH --deleteVendorPackages=false
	// This is the key difference from runPrefixing() which passes no args
	$php = PHP_BINARY ?: 'php';
	$commandRunner->run(
		[ $php, $straussBinary, '--deleteVendorPackages=false' ],
		$projectRoot
	);

	$logger( '' );

	// Verify vendor_prefixed was created
	if ( \is_dir( $vendorPrefixedDir ) ) {
		$logger( '=== Success ===' );
		$logger( 'vendor_prefixed/ created alongside vendor/' );
		$logger( '' );
		$logger( 'AptowebDeps\\* prefixed classes are now available.' );
		$logger( 'Both autoloaders will be loaded by plugin_autoload.php.' );
		$logger( '' );
		$logger( 'To clean up later, run: php bin/run-strauss-dev.php --clean' );
	}
	else {
		throw new \RuntimeException(
			'Strauss completed but vendor_prefixed/ was not created. '.
			'Check the Strauss output above for errors.'
		);
	}

	exit( 0 );
}
catch ( Throwable $throwable ) {
	\fwrite( STDERR, 'Error: '.$throwable->getMessage().PHP_EOL );
	exit( 1 );
}
