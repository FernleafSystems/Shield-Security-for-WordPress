#!/usr/bin/env php
<?php declare( strict_types=1 );

use Symfony\Component\Filesystem\Path;

/**
 * Synchronize the WordPress.org plugin preview blueprint into an SVN checkout root.
 *
 * Copies:
 *   infrastructure/wordpress-org/blueprints/blueprint.json
 * To:
 *   <svn-root>/assets/blueprints/blueprint.json
 *
 * Notes:
 * - <svn-root> should contain the WordPress.org plugin repo layout: trunk/, tags/, assets/
 * - This script intentionally does not commit SVN changes.
 */

require dirname( __DIR__ ).'/vendor/autoload.php';

$options = getopt( '', [
	'svn-root:',
	'source::',
	'check-only',
	'help',
] );

if ( isset( $options['help'] ) ) {
	echo usage();
	exit( 0 );
}

$projectRoot = Path::normalize( dirname( __DIR__ ) );
$defaultSource = Path::join( $projectRoot, 'infrastructure', 'wordpress-org', 'blueprints', 'blueprint.json' );
$source = (string)( $options['source'] ?? $defaultSource );
$svnRoot = isset( $options['svn-root'] ) ? (string)$options['svn-root'] : '';
$checkOnly = isset( $options['check-only'] );

if ( $svnRoot === '' ) {
	fwrite( STDERR, "Error: --svn-root is required.\n\n".usage() );
	exit( 1 );
}

$source = normalizePath( $source );
$svnRoot = normalizePath( $svnRoot );

if ( !file_exists( $source ) ) {
	fwrite( STDERR, "Error: Source blueprint file not found: {$source}\n" );
	exit( 1 );
}
if ( !is_file( $source ) ) {
	fwrite( STDERR, "Error: Source path is not a file: {$source}\n" );
	exit( 1 );
}
if ( !is_dir( $svnRoot ) ) {
	fwrite( STDERR, "Error: SVN root directory not found: {$svnRoot}\n" );
	exit( 1 );
}
if ( !is_dir( Path::join( $svnRoot, 'trunk' ) ) || !is_dir( Path::join( $svnRoot, 'tags' ) ) ) {
	fwrite(
		STDERR,
		"Error: --svn-root does not look like a WordPress.org plugin SVN root (missing trunk/ or tags/): {$svnRoot}\n"
	);
	exit( 1 );
}

$sourceContent = file_get_contents( $source );
if ( $sourceContent === false ) {
	fwrite( STDERR, "Error: Failed to read source blueprint file: {$source}\n" );
	exit( 1 );
}

$decoded = json_decode( $sourceContent, true );
if ( !is_array( $decoded ) || json_last_error() !== JSON_ERROR_NONE ) {
	fwrite( STDERR, "Error: Source blueprint is not valid JSON: ".json_last_error_msg()."\n" );
	exit( 1 );
}

$destination = Path::join( $svnRoot, 'assets', 'blueprints', 'blueprint.json' );
$destinationDir = dirname( $destination );

if ( $checkOnly ) {
	echo "Check-only mode enabled.\n";
	if ( !file_exists( $destination ) ) {
		fwrite( STDERR, "Blueprint destination missing: {$destination}\n" );
		exit( 2 );
	}

	$destinationContent = file_get_contents( $destination );
	if ( $destinationContent === false ) {
		fwrite( STDERR, "Failed to read destination blueprint: {$destination}\n" );
		exit( 2 );
	}

	if ( hash( 'sha256', $sourceContent ) !== hash( 'sha256', $destinationContent ) ) {
		fwrite( STDERR, "Blueprint destination differs from source: {$destination}\n" );
		exit( 2 );
	}

	echo "Blueprint is in sync: {$destination}\n";
	exit( 0 );
}

if ( !is_dir( $destinationDir ) && !mkdir( $destinationDir, 0775, true ) && !is_dir( $destinationDir ) ) {
	fwrite( STDERR, "Error: Failed to create destination directory: {$destinationDir}\n" );
	exit( 1 );
}

if ( file_put_contents( $destination, $sourceContent ) === false ) {
	fwrite( STDERR, "Error: Failed to write destination blueprint: {$destination}\n" );
	exit( 1 );
}

echo "Blueprint synchronized successfully.\n";
echo "Source: {$source}\n";
echo "Destination: {$destination}\n";
echo "Next: review with 'svn status' and commit in the SVN working copy.\n";

exit( 0 );

function normalizePath( string $path ) :string {
	$trimmed = trim( $path, " \t\n\r\0\x0B\"'" );
	if ( $trimmed === '' ) {
		return '';
	}
	return Path::normalize( str_replace( '\\', '/', $trimmed ) );
}

function usage() :string {
	return <<<TXT
Usage:
  php bin/sync-wporg-blueprint.php --svn-root=<path> [--source=<path>] [--check-only]

Options:
  --svn-root   Path to WordPress.org plugin SVN root (must contain trunk/ and tags/).
  --source     Optional blueprint source file path.
               Default: infrastructure/wordpress-org/blueprints/blueprint.json
  --check-only Validate destination exists and is byte-identical to source, without copying.
  --help       Show this help.

Examples:
  php bin/sync-wporg-blueprint.php --svn-root=/path/to/wporg-svn/wp-simple-firewall
  php bin/sync-wporg-blueprint.php --svn-root=/path/to/wporg-svn/wp-simple-firewall --check-only
TXT;
}
