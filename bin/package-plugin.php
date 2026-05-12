#!/usr/bin/env php
<?php
declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PackagerConfigResolver;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PluginPackager;
use Symfony\Component\Filesystem\Path;

require dirname( __DIR__ ).'/vendor/autoload.php';

$options = getopt( '', [
	'output::',
	'strauss-version::',
	'strauss-fork-repo::',
	'strauss-fork-branch::',
	'skip-root-composer',
	'skip-lib-composer',
	'skip-package-dependency-build',
	'skip-npm-install',
	'skip-npm-build',
	'skip-directory-clean',
	'skip-copy',
	'version::',
	'release-timestamp::',
	'build::',
] );

$outputDir = $options[ 'output' ] ?? null;
if ( is_string( $outputDir ) ) {
	$outputDir = Path::normalize( trim( $outputDir, " \t\n\r\0\x0B\"'" ) );
}

$projectRoot = Path::normalize( dirname( __DIR__ ) );
$packagerConfig = ( new PackagerConfigResolver() )->resolve( $projectRoot );

// Resolve Strauss version: CLI arg > env var/config file > null (fallback in PluginPackager)
$straussVersion = $options[ 'strauss-version' ] ?? null;
$resolvedStrauss = null;
if ( is_string( $straussVersion ) && $straussVersion !== '' ) {
	$resolvedStrauss = ltrim( trim( $straussVersion ), 'v' );
}
else {
	$resolvedStrauss = $packagerConfig[ 'strauss_version' ];
}

$packagerOptions = [];

if ( isset( $options[ 'skip-root-composer' ] ) ) {
	$packagerOptions[ 'composer_install' ] = false;
}

if ( isset( $options[ 'skip-lib-composer' ] ) ) {
	// Legacy flag retained for backwards compatibility with existing scripts.
	// Package dependency work is now controlled by --skip-package-dependency-build.
}

if ( isset( $options[ 'skip-package-dependency-build' ] ) ) {
	$packagerOptions[ 'package_dependency_build' ] = false;
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

// Resolve fork repo: CLI arg > env var/config file > null
$straussForkRepo = $options[ 'strauss-fork-repo' ] ?? null;
if ( !is_string( $straussForkRepo ) || $straussForkRepo === '' ) {
	$straussForkRepo = $packagerConfig[ 'strauss_fork_repo' ];
}

if ( $straussForkRepo !== null ) {
	$packagerOptions[ 'strauss_fork_repo' ] = trim( $straussForkRepo );

	$straussForkBranch = $options[ 'strauss-fork-branch' ] ?? null;
	if ( !is_string( $straussForkBranch ) || $straussForkBranch === '' ) {
		$straussForkBranch = $packagerConfig[ 'strauss_fork_branch' ] ?? 'develop';
	}
	$packagerOptions[ 'strauss_fork_branch' ] = trim( $straussForkBranch );
}

// Version metadata options
if ( isset( $options[ 'version' ] ) && is_string( $options[ 'version' ] ) ) {
	$packagerOptions[ 'version' ] = trim( $options[ 'version' ] );
}

if ( isset( $options[ 'release-timestamp' ] ) ) {
	$packagerOptions[ 'release_timestamp' ] = (int)$options[ 'release-timestamp' ];
}

if ( isset( $options[ 'build' ] ) && is_string( $options[ 'build' ] ) ) {
	$packagerOptions[ 'build' ] = trim( $options[ 'build' ] );
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
