#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\PluginPackager;
use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\SafeDirectoryRemover;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PackagerConfig;
use Symfony\Component\Filesystem\Path;

require dirname( __DIR__ ).'/vendor/autoload.php';

$options = getopt( '', [
	'output::',
	'zip-root-folder::',
	'strauss-version::',
	'strauss-fork-repo::',
	'skip-root-composer',
	'skip-lib-composer',
	'skip-npm-install',
	'skip-npm-build',
	'keep-package',
	'version::',
	'release-timestamp::',
	'build::',
] );

// Resolve Strauss version: CLI arg > env var/config file (via PackagerConfig) > null (fallback in PluginPackager)
$straussVersion = $options[ 'strauss-version' ] ?? null;
$resolvedStrauss = null;
if ( \is_string( $straussVersion ) && $straussVersion !== '' ) {
	$resolvedStrauss = \ltrim( \trim( $straussVersion ), 'v' );
}
else {
	$resolvedStrauss = PackagerConfig::getStraussVersion();
}

$packagerOptions = [];

if ( isset( $options[ 'skip-root-composer' ] ) ) {
	$packagerOptions[ 'composer_root' ] = false;
}

if ( isset( $options[ 'skip-lib-composer' ] ) ) {
	$packagerOptions[ 'composer_lib' ] = false;
}

if ( isset( $options[ 'skip-npm-install' ] ) ) {
	$packagerOptions[ 'npm_install' ] = false;
}

if ( isset( $options[ 'skip-npm-build' ] ) ) {
	$packagerOptions[ 'npm_build' ] = false;
}

if ( \is_string( $resolvedStrauss ) && $resolvedStrauss !== '' ) {
	$packagerOptions[ 'strauss_version' ] = $resolvedStrauss;
}

// Resolve fork repo: CLI arg > env var/config file (via PackagerConfig) > null
$straussForkRepo = $options[ 'strauss-fork-repo' ] ?? null;
if ( !\is_string( $straussForkRepo ) || $straussForkRepo === '' ) {
	$straussForkRepo = PackagerConfig::getStraussForkRepo();
}

if ( $straussForkRepo !== null ) {
	$packagerOptions[ 'strauss_fork_repo' ] = \trim( $straussForkRepo );
}

// Version metadata options
if ( isset( $options[ 'version' ] ) && \is_string( $options[ 'version' ] ) ) {
	$packagerOptions[ 'version' ] = \trim( $options[ 'version' ] );
}

if ( isset( $options[ 'release-timestamp' ] ) ) {
	$packagerOptions[ 'release_timestamp' ] = (int)$options[ 'release-timestamp' ];
}

if ( isset( $options[ 'build' ] ) && \is_string( $options[ 'build' ] ) ) {
	$packagerOptions[ 'build' ] = \trim( $options[ 'build' ] );
}

// Resolve zip root folder name (the folder name inside the zip archive)
$zipRootFolder = $options[ 'zip-root-folder' ] ?? null;
if ( !\is_string( $zipRootFolder ) || \trim( $zipRootFolder ) === '' ) {
	$zipRootFolder = 'wp-simple-firewall';
}
else {
	$zipRootFolder = \trim( $zipRootFolder );
}

// Create temp directory for intermediate package
$tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'shield-build-'.\bin2hex( \random_bytes( 8 ) );
$keepPackage = isset( $options[ 'keep-package' ] );
$projectRoot = dirname( __DIR__ );

$exitCode = 0;
$packagePath = null;

try {
	// Build the package into temp directory
	echo "📦 Building Shield plugin package...\n";
	$packagePath = ( new PluginPackager() )->package( $tempDir, $packagerOptions );
	echo "✅ Package built at: {$packagePath}\n";

	// Determine output zip path
	$outputZip = $options[ 'output' ] ?? null;
	if ( \is_string( $outputZip ) ) {
		$outputZip = Path::normalize( \trim( $outputZip, " \t\n\r\0\x0B\"'" ) );
	}
	else {
		// Default: builds/wp-simple-firewall-{timestamp}.zip
		$buildsDir = $projectRoot.DIRECTORY_SEPARATOR.'builds';
		if ( !\is_dir( $buildsDir ) && !\mkdir( $buildsDir, 0755, true ) ) {
			throw new \RuntimeException( 'Failed to create builds directory: '.$buildsDir );
		}
		$timestamp = \date( 'Ymd-His' );
		$outputZip = $buildsDir.DIRECTORY_SEPARATOR.'wp-simple-firewall-'.$timestamp.'.zip';
	}

	// Create the zip file
	echo "🗜️  Creating zip archive...\n";
	$zip = new ZipArchive();
	$result = $zip->open( $outputZip, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	if ( $result !== true ) {
		throw new \RuntimeException( 'Failed to create zip file: '.$outputZip.' (error code: '.$result.')' );
	}

	// Add all files from package directory with root folder prefix
	$packageDir = $packagePath;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $packageDir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);

	$fileCount = 0;
	foreach ( $iterator as $file ) {
		if ( $file->isFile() ) {
			$filePath = $file->getRealPath();
			$relativePath = \substr( $filePath, \strlen( $packageDir ) + 1 );
			// Normalize path separators to forward slashes for zip
			$relativePath = \str_replace( '\\', '/', $relativePath );
			// Add root folder prefix
			$zipPath = Path::join( $zipRootFolder, $relativePath );
			$zip->addFile( $filePath, $zipPath );
			$fileCount++;
		}
	}

	$zip->close();
	echo "✅ Zip created: {$outputZip}\n";
	echo "   📊 Files included: {$fileCount}\n";

	if ( $keepPackage ) {
		echo "📁 Keeping intermediate package at: {$packagePath}\n";
	}
}
catch ( Throwable $throwable ) {
	fwrite( STDERR, '❌ Build failed: '.$throwable->getMessage().PHP_EOL );
	$exitCode = 1;
}
finally {
	// Cleanup temp directory unless --keep-package was specified
	if ( !$keepPackage && \is_dir( $tempDir ) ) {
		echo "🧹 Cleaning up temporary files...\n";
		try {
			( new SafeDirectoryRemover( $projectRoot ) )->removeTempDirectory( $tempDir );
		}
		catch ( Throwable $cleanupError ) {
			fwrite( STDERR, '⚠️  Cleanup warning: '.$cleanupError->getMessage().PHP_EOL );
		}
	}
}

exit( $exitCode );
