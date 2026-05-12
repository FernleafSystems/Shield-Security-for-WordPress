#!/usr/bin/env php
<?php declare( strict_types=1 );

use Symfony\Component\Process\Process;

const EXIT_PASS = 0;
const EXIT_FAIL = 1;
const EXIT_ENV = 2;

$autoloadPath = dirname( __DIR__ ).'/vendor/autoload.php';
if ( !is_file( $autoloadPath ) ) {
	fwrite( STDERR, "Dependencies are not installed. Run 'composer install' first.\n" );
	exit( EXIT_ENV );
}
require $autoloadPath;

$args = $argv;
array_shift( $args );

if ( empty( $args ) || in_array( $args[0], [ '--help', '-h' ], true ) ) {
	echo usage();
	exit( EXIT_PASS );
}

$tool = (string)array_shift( $args );
$projectRoot = dirname( __DIR__ );
$nodeVersion = readPinnedNodeVersion( $projectRoot );
$currentNode = findCurrentNodeBinary( $nodeVersion );
$nodeBinary = $currentNode ?? findPinnedNodeBinary( $nodeVersion );

if ( $nodeBinary === null ) {
	fwrite(
		STDERR,
		sprintf(
			"Unable to resolve a supported Node.js %s binary. Install it locally or set SHIELD_NODE_BINARY.\n",
			$nodeVersion
		)
	);
	exit( EXIT_ENV );
}

$toolCommand = resolveToolCommand( $projectRoot, $tool, $args );
if ( $toolCommand === null ) {
	fwrite( STDERR, "Unsupported tool '{$tool}'. Supported tools: playwright.\n" );
	exit( EXIT_ENV );
}

$command = array_merge( [ $nodeBinary, $toolCommand['script'] ], $toolCommand['args'] );
$process = new Process( $command, $projectRoot, buildProcessEnvironment( $nodeBinary ) );
$process->setTimeout( null );
$process->run( static function ( string $type, string $buffer ) :void {
	if ( $type === Process::ERR ) {
		fwrite( STDERR, $buffer );
	}
	else {
		echo $buffer;
	}
} );

exit( $process->getExitCode() ?? EXIT_FAIL );

/**
 * @return array{script:string,args:string[]}|null
 */
function resolveToolCommand( string $projectRoot, string $tool, array $args ) :?array {
	$toolMap = [
		'playwright' => $projectRoot.'/node_modules/@playwright/test/cli.js',
	];

	$script = $toolMap[ $tool ] ?? null;
	if ( $script === null || !is_file( $script ) ) {
		return null;
	}

	return [
		'script' => $script,
		'args' => array_values( array_map( 'strval', $args ) ),
	];
}

/**
 * @return array<string,string>
 */
function buildProcessEnvironment( string $nodeBinary ) :array {
	$environment = getenv();
	if ( !is_array( $environment ) ) {
		$environment = [];
	}

	$pathParts = [];
	$nodeDir = dirname( $nodeBinary );
	if ( $nodeDir !== '' && $nodeDir !== '.' ) {
		$pathParts[] = $nodeDir;
	}
	$currentPath = getenv( 'PATH' );
	if ( is_string( $currentPath ) && $currentPath !== '' ) {
		$pathParts[] = $currentPath;
	}

	$environment['PATH'] = implode( PATH_SEPARATOR, $pathParts );
	return $environment;
}

function readPinnedNodeVersion( string $projectRoot ) :string {
	$nvmrcPath = $projectRoot.'/.nvmrc';
	if ( !is_file( $nvmrcPath ) ) {
		return '20.10.0';
	}

	$version = trim( (string)file_get_contents( $nvmrcPath ) );
	return $version !== '' ? $version : '20.10.0';
}

function findCurrentNodeBinary( string $pinnedVersion ) :?string {
	$override = getenv( 'SHIELD_NODE_BINARY' );
	if ( is_string( $override ) && $override !== '' && is_file( $override ) && isSupportedNodeBinary( $override, $pinnedVersion ) ) {
		return $override;
	}

	$currentBinary = resolveNodeFromPath();
	if ( $currentBinary === null ) {
		return null;
	}

	$process = new Process( [ $currentBinary, '-v' ] );
	$process->setTimeout( 5 );

	try {
		$process->run();
	}
	catch ( Throwable $e ) {
		return null;
	}

	if ( !$process->isSuccessful() ) {
		return null;
	}

	return isSupportedNodeVersion( trim( $process->getOutput() ), $pinnedVersion ) ? $currentBinary : null;
}

function resolveNodeFromPath() :?string {
	$command = \PHP_OS_FAMILY === 'Windows' ? [ 'where', 'node' ] : [ 'which', 'node' ];
	$process = new Process( $command );
	$process->setTimeout( 5 );

	try {
		$process->run();
	}
	catch ( Throwable $e ) {
		return null;
	}

	if ( !$process->isSuccessful() ) {
		return null;
	}

	$lines = preg_split( '/\r\n|\r|\n/', trim( $process->getOutput() ) ) ?: [];
	foreach ( $lines as $line ) {
		$candidate = trim( $line );
		if ( $candidate !== '' && is_file( $candidate ) ) {
			return $candidate;
		}
	}

	return null;
}

function findPinnedNodeBinary( string $pinnedVersion ) :?string {
	$override = getenv( 'SHIELD_NODE_BINARY' );
	if ( is_string( $override ) && $override !== '' && is_file( $override ) ) {
		return $override;
	}

	$candidates = [];
	$windowsBinary = 'node.exe';
	$unixBinary = 'node';

	if ( \PHP_OS_FAMILY === 'Windows' ) {
		$nvmHome = getenv( 'NVM_HOME' );
		if ( is_string( $nvmHome ) && $nvmHome !== '' ) {
			$candidates[] = normalizePath( $nvmHome ).'/v'.$pinnedVersion.'/'.$windowsBinary;
		}

		$nvmRoot = probeNvmRoot();
		if ( $nvmRoot !== null ) {
			$candidates[] = normalizePath( $nvmRoot ).'/v'.$pinnedVersion.'/'.$windowsBinary;
		}
	}
	else {
		$nvmDir = getenv( 'NVM_DIR' );
		if ( is_string( $nvmDir ) && $nvmDir !== '' ) {
			$candidates[] = normalizePath( $nvmDir ).'/versions/node/v'.$pinnedVersion.'/bin/'.$unixBinary;
		}

		$homeDir = getenv( 'HOME' );
		if ( is_string( $homeDir ) && $homeDir !== '' ) {
			$candidates[] = normalizePath( $homeDir ).'/.nvm/versions/node/v'.$pinnedVersion.'/bin/'.$unixBinary;
		}
	}

	foreach ( $candidates as $candidate ) {
		if ( is_file( $candidate ) && isSupportedNodeBinary( $candidate, $pinnedVersion ) ) {
			return $candidate;
		}
	}

	return null;
}

function probeNvmRoot() :?string {
	$process = new Process( [ 'nvm', 'root' ] );
	$process->setTimeout( 5 );

	try {
		$process->run();
	}
	catch ( Throwable $e ) {
		return null;
	}

	if ( !$process->isSuccessful() ) {
		return null;
	}

	$matches = [];
	if ( preg_match( '/Current Root:\s*(.+)$/m', $process->getOutput(), $matches ) === 1 ) {
		return trim( $matches[1] );
	}

	return null;
}

function isSupportedNodeBinary( string $nodeBinary, string $pinnedVersion ) :bool {
	$process = new Process( [ $nodeBinary, '-v' ] );
	$process->setTimeout( 5 );

	try {
		$process->run();
	}
	catch ( Throwable $e ) {
		return false;
	}

	return $process->isSuccessful() && isSupportedNodeVersion( trim( $process->getOutput() ), $pinnedVersion );
}

function isSupportedNodeVersion( string $rawVersion, string $pinnedVersion ) :bool {
	if ( preg_match( '/^v?(\d+)\.(\d+)\.(\d+)$/', trim( $rawVersion ), $matches ) !== 1 ) {
		return false;
	}

	$pinnedParts = explode( '.', ltrim( $pinnedVersion, 'v' ) );
	$requiredMajor = (int)( $pinnedParts[0] ?? 20 );
	$requiredMinor = (int)( $pinnedParts[1] ?? 10 );
	$major = (int)$matches[1];
	$minor = (int)$matches[2];

	return $major === $requiredMajor && $minor >= $requiredMinor;
}

function normalizePath( string $path ) :string {
	return rtrim( str_replace( '\\', '/', $path ), '/' );
}

function usage() :string {
	return <<<TXT
Usage:
  php bin/run-node-tool.php <playwright> [args...]

Purpose:
  Run browser and tooling commands with the repo's supported Node version from .nvmrc
  without changing the machine's default Node selection.

Environment:
  SHIELD_NODE_BINARY  Optional explicit path to a supported node binary.

Examples:
  php bin/run-node-tool.php playwright test --workers=1
  php bin/run-node-tool.php playwright install chromium
TXT;
}
