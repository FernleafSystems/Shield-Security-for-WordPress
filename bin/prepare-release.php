#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\VersionUpdater;

require dirname( __DIR__ ).'/vendor/autoload.php';

$options = getopt( '', [
	'version::',
	'release-timestamp::',
	'build::',
	'help',
] );

if ( isset( $options[ 'help' ] ) ) {
	echo <<<USAGE
Usage: php bin/prepare-release.php [OPTIONS]

Prepares source files for a new release by updating version metadata.

Options:
  --version=<VERSION>             Version number (e.g., "21.1.2")
  --release-timestamp=<INT>       Unix timestamp (auto-generated if version provided)
  --build=<BUILD|auto>            Build number (YYYYMM.DDBB) or "auto" to generate
  --help                          Show this help message

Examples:
  php bin/prepare-release.php --version=21.1.2 --build=auto
  php bin/prepare-release.php --version=21.1.2 --build=202602.0401
  php bin/prepare-release.php --build=auto

Files updated:
  - plugin-spec/01_properties.json (version, release_timestamp, build)
  - readme.txt (Stable tag)
  - icwp-wpsf.php (Version header)

USAGE;
	exit( 0 );
}

$projectRoot = dirname( __DIR__ );
$logger = static function ( string $message ) :void {
	echo $message.PHP_EOL;
};

$versionUpdater = new VersionUpdater( $projectRoot, $logger );

// Build version options array (same logic as PluginPackager::updateSourceSpec)
$versionOptions = [];

if ( isset( $options[ 'version' ] ) && \is_string( $options[ 'version' ] ) ) {
	$versionOptions[ 'version' ] = \trim( $options[ 'version' ] );

	// Auto-generate timestamp if version provided but timestamp not
	if ( !isset( $options[ 'release-timestamp' ] ) ) {
		$versionOptions[ 'release_timestamp' ] = \time();
	}
}

if ( isset( $options[ 'release-timestamp' ] ) ) {
	$versionOptions[ 'release_timestamp' ] = (int)$options[ 'release-timestamp' ];
}

if ( isset( $options[ 'build' ] ) && \is_string( $options[ 'build' ] ) ) {
	$build = \trim( $options[ 'build' ] );
	if ( $build === 'auto' ) {
		$versionOptions[ 'build' ] = $versionUpdater->generateBuild();
	}
	else {
		$versionOptions[ 'build' ] = $build;
	}
}

if ( empty( $versionOptions ) ) {
	fwrite( STDERR, "Error: No options provided. Use --help for usage information.\n" );
	exit( 1 );
}

$exitCode = 0;

try {
	echo "Preparing release...\n";

	// Update source spec file
	$versionUpdater->updateSourceProperties( $versionOptions );

	// Update readme.txt and icwp-wpsf.php if version was provided
	if ( isset( $versionOptions[ 'version' ] ) ) {
		$versionUpdater->updateReadmeTxt( $projectRoot, $versionOptions[ 'version' ] );
		$versionUpdater->updatePluginHeader( $projectRoot, $versionOptions[ 'version' ] );
	}

	echo "Release preparation complete.\n";
}
catch ( Throwable $throwable ) {
	fwrite( STDERR, 'Error: '.$throwable->getMessage().PHP_EOL );
	$exitCode = 1;
}

exit( $exitCode );
