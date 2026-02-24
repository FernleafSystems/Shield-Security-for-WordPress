<?php declare( strict_types=1 );

$sourceRoot = \dirname( __DIR__, 2 );
$packageRootEnv = \getenv( 'SHIELD_PACKAGE_PATH' );

if ( !\is_string( $packageRootEnv ) || $packageRootEnv === '' ) {
	throw new \RuntimeException(
		'SHIELD_PACKAGE_PATH is not set. Run packaged analysis through bin/run-docker-tests.sh --analyze-package.'
	);
}

$packageRoot = \rtrim( $packageRootEnv, "/\\" );

// Intentional manual join: this bootstrap validates autoload files before any autoloader is required.
$sourceVendorAutoload = $sourceRoot.'/vendor/autoload.php';
// Intentional manual join: this bootstrap validates autoload files before any autoloader is required.
$packageVendorAutoload = $packageRoot.'/vendor/autoload.php';
// Intentional manual join: this bootstrap validates autoload files before any autoloader is required.
$packagePrefixedAutoload = $packageRoot.'/vendor_prefixed/autoload.php';

$requiredFiles = [
	$sourceVendorAutoload => 'source vendor autoloader',
	$packageVendorAutoload => 'package vendor autoloader',
	$packagePrefixedAutoload => 'package vendor_prefixed autoloader',
];

foreach ( $requiredFiles as $path => $label ) {
	if ( !\is_file( $path ) ) {
		throw new \RuntimeException(
			\sprintf( 'Missing %s: %s', $label, $path )
		);
	}
}

// Intentional manual joins: these paths are resolved before Symfony classes could be autoloaded.
require_once $sourceVendorAutoload;
require_once $packagePrefixedAutoload;
