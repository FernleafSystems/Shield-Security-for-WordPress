<?php declare( strict_types=1 );

$sourceRoot = \dirname( __DIR__, 2 );
$packageRootEnv = \getenv( 'SHIELD_PACKAGE_PATH' );

if ( !\is_string( $packageRootEnv ) || $packageRootEnv === '' ) {
	throw new \RuntimeException(
		'SHIELD_PACKAGE_PATH is not set. Run packaged analysis through bin/run-docker-tests.sh --analyze-package.'
	);
}

$packageRoot = \rtrim( $packageRootEnv, "/\\" );

$requiredFiles = [
	$sourceRoot.'/vendor/autoload.php'         => 'source vendor autoloader',
	$packageRoot.'/vendor/autoload.php'        => 'package vendor autoloader',
	$packageRoot.'/vendor_prefixed/autoload.php' => 'package vendor_prefixed autoloader',
];

foreach ( $requiredFiles as $path => $label ) {
	if ( !\is_file( $path ) ) {
		throw new \RuntimeException(
			\sprintf( 'Missing %s: %s', $label, $path )
		);
	}
}

require_once $sourceRoot.'/vendor/autoload.php';
require_once $packageRoot.'/vendor_prefixed/autoload.php';
