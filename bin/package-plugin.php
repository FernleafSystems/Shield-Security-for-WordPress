#!/usr/bin/env php
<?php
declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

require dirname( __DIR__ ).'/vendor/autoload.php';

$options = getopt( '', [
	'output::',
	'strauss-version::',
	'skip-root-composer',
	'skip-lib-composer',
	'skip-npm-install',
	'skip-npm-build',
	'skip-directory-clean',
	'skip-copy',
] );

$outputDir = $options[ 'output' ] ?? null;
// Trim quotes and whitespace from output directory path
if ( is_string( $outputDir ) ) {
	$outputDir = trim( $outputDir, " \t\n\r\0\x0B\"'" );
}

$straussVersion = $options[ 'strauss-version' ] ?? null;
if ( is_string( $straussVersion ) ) {
	$straussVersion = trim( $straussVersion );
}
$envStrauss = getenv( 'SHIELD_STRAUSS_VERSION' );
$resolvedStrauss = $straussVersion !== null && $straussVersion !== ''
	? $straussVersion
	: ( is_string( $envStrauss ) ? $envStrauss : null );

$packagerOptions = [];

if ( isset( $options[ 'skip-root-composer' ] ) ) {
	$packagerOptions[ 'composer_root' ] = false;
}

if ( isset( $options['skip-lib-composer'] ) ) {
	$packagerOptions['composer_lib'] = false;
}

if ( isset( $options[ 'skip-npm-install' ] ) ) {
	$packagerOptions[ 'npm_install' ] = false;
}

if ( isset( $options[ 'skip-npm-build' ] ) ) {
	$packagerOptions[ 'npm_build' ] = false;
}

if ( isset( $options[ 'skip-directory-clean' ] ) ) {
	$packagerOptions[ 'directory_clean' ] = false;
}

if ( isset( $options[ 'skip-copy' ] ) ) {
	$packagerOptions[ 'skip_copy' ] = true;
}

if ( is_string( $resolvedStrauss ) && $resolvedStrauss !== '' ) {
	$packagerOptions[ 'strauss_version' ] = $resolvedStrauss;
}

try {
	$path = ( new PluginPackager() )->package( is_string( $outputDir ) ? $outputDir : null, $packagerOptions );
	echo '✅ Shield plugin package created at: '.$path.PHP_EOL;
	exit( 0 );
}
catch ( Throwable $throwable ) {
	fwrite( STDERR, '❌ Packaging failed: '.$throwable->getMessage().PHP_EOL );
	exit( 1 );
}
