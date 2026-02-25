#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\Process\BashCommandResolver;
use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Filesystem\Path;

require dirname( __DIR__ ).'/vendor/autoload.php';

const SHIELD_DOCKER_TEST_MODES = [
	'--source' => 'source',
	'--package-targeted' => 'package-targeted',
	'--package-full' => 'package-full',
	'--analyze-source' => 'analyze-source',
	'--analyze-package' => 'analyze-package',
];

$rootDir = Path::normalize( dirname( __DIR__ ) );
$args = array_slice( $_SERVER['argv'] ?? [], 1 );
$parseResult = parseArgs( $args );

if ( $parseResult[ 'help' ] === true ) {
	writeHelp();
	exit( 0 );
}

if ( $parseResult[ 'error' ] !== null ) {
	fwrite( STDERR, 'Error: '.$parseResult[ 'error' ].PHP_EOL );
	fwrite( STDERR, 'Use --help for usage.'.PHP_EOL );
	exit( 1 );
}

$mode = $parseResult[ 'mode' ] ?? 'source';
$runner = new ProcessRunner();
$bashCommandResolver = new BashCommandResolver();

try {
	switch ( $mode ) {
		case 'analyze-source':
			exit( runSourceAnalysis( $runner, $rootDir ) );

		case 'analyze-package':
			exit( runLegacyPackagedMode( $runner, $rootDir, [ '--analyze-package' ], $bashCommandResolver ) );

		case 'package-targeted':
			fwrite( STDOUT, 'Mode: package-targeted'.PHP_EOL );
			exit( runLegacyPackagedMode( $runner, $rootDir, [], $bashCommandResolver ) );

		case 'package-full':
			fwrite( STDOUT, 'Mode: package-full'.PHP_EOL );
			exit( runLegacyPackagedMode( $runner, $rootDir, [], $bashCommandResolver ) );

		case 'source':
		default:
			exit( runSourceRuntimeMode( $runner, $rootDir, $bashCommandResolver ) );
	}
}
catch ( Throwable $throwable ) {
	fwrite( STDERR, 'Error: '.$throwable->getMessage().PHP_EOL );
	exit( 1 );
}

/**
 * @return array{help:bool,error:?string,mode:?string}
 */
function parseArgs( array $args ) :array {
	$wantsHelp = false;
	$selectedMode = null;

	foreach ( $args as $arg ) {
		if ( $arg === '--help' || $arg === '-h' ) {
			$wantsHelp = true;
			continue;
		}

		if ( !isset( SHIELD_DOCKER_TEST_MODES[ $arg ] ) ) {
			return [
				'help' => false,
				'error' => 'Unknown argument: '.$arg,
				'mode' => null,
			];
		}

		$mode = SHIELD_DOCKER_TEST_MODES[ $arg ];
		if ( $selectedMode !== null && $selectedMode !== $mode ) {
			return [
				'help' => false,
				'error' => 'Only one mode flag can be provided at a time.',
				'mode' => null,
			];
		}
		$selectedMode = $mode;
	}

	if ( $wantsHelp ) {
		return [
			'help' => true,
			'error' => null,
			'mode' => null,
		];
	}

	return [
		'help' => false,
		'error' => null,
		'mode' => $selectedMode,
	];
}

function writeHelp() :void {
	fwrite( STDOUT, 'Usage: ./bin/run-docker-tests.sh [--source|--package-targeted|--package-full|--analyze-source|--analyze-package]'.PHP_EOL );
	fwrite( STDOUT, PHP_EOL );
	fwrite( STDOUT, 'Modes:'.PHP_EOL );
	fwrite( STDOUT, '  (default)         Source runtime checks against working tree'.PHP_EOL );
	fwrite( STDOUT, '  --source          Source runtime checks against working tree'.PHP_EOL );
	fwrite( STDOUT, '  --package-targeted Build package and run packaged runtime checks'.PHP_EOL );
	fwrite( STDOUT, '  --package-full    Build package and run full packaged pathway'.PHP_EOL );
	fwrite( STDOUT, '  --analyze-source  Run source static analysis pathway'.PHP_EOL );
	fwrite( STDOUT, '  --analyze-package Build package and run packaged static analysis'.PHP_EOL );
	fwrite( STDOUT, PHP_EOL );
	fwrite( STDOUT, 'Source defaults:'.PHP_EOL );
	fwrite( STDOUT, '  - Uses working tree changes (no HEAD snapshot export)'.PHP_EOL );
	fwrite( STDOUT, '  - Does not run composer package-plugin in source mode'.PHP_EOL );
}

function runSourceAnalysis( ProcessRunner $runner, string $rootDir ) :int {
	fwrite( STDOUT, 'Mode: analyze-source'.PHP_EOL );
	$process = $runner->run(
		[ PHP_BINARY, './bin/run-static-analysis.php', '--source' ],
		$rootDir
	);
	return $process->getExitCode() ?? 1;
}

function runLegacyPackagedMode(
	ProcessRunner $runner,
	string $rootDir,
	array $extraArgs,
	BashCommandResolver $bashCommandResolver
) :int {
	$legacyScriptRelative = './bin/run-docker-tests.legacy.sh';
	$legacyScript = Path::join( $rootDir, 'bin', 'run-docker-tests.legacy.sh' );
	if ( !is_file( $legacyScript ) ) {
		fwrite( STDERR, 'Error: Legacy runner script not found: '.$legacyScript.PHP_EOL );
		return 1;
	}

	$command = array_merge(
		[ $bashCommandResolver->resolve(), $legacyScriptRelative ],
		$extraArgs
	);

	$process = $runner->run( $command, $rootDir );
	return $process->getExitCode() ?? 1;
}

function runSourceRuntimeMode(
	ProcessRunner $runner,
	string $rootDir,
	BashCommandResolver $bashCommandResolver
) :int {
	fwrite( STDOUT, 'Mode: source'.PHP_EOL );
	$originalShieldPackagePath = getenv( 'SHIELD_PACKAGE_PATH' );
	$hasOriginalShieldPackagePath = is_string( $originalShieldPackagePath );
	putenv( 'SHIELD_PACKAGE_PATH' );

	try {
		if ( !isDockerAvailable( $runner, $rootDir ) ) {
			fwrite( STDERR, 'Error: Docker is required but not found in PATH.'.PHP_EOL );
			return 1;
		}
		if ( !isDockerDaemonRunning( $runner, $rootDir ) ) {
			fwrite( STDERR, 'Error: Docker daemon is not running.'.PHP_EOL );
			return 1;
		}

		$defaultPhp = readDefaultPhpFromMatrixConfig( $rootDir );
		$phpVersion = trim( (string)( getenv( 'PHP_VERSION' ) ?: '' ) );
		if ( $phpVersion === '' ) {
			$phpVersion = $defaultPhp;
		}

		[ $latestWpVersion, $previousWpVersion ] = detectWordpressVersions( $runner, $rootDir, $bashCommandResolver );

		$dockerEnvPath = Path::join( $rootDir, 'tests', 'docker', '.env' );
		writeSourceDockerEnvFile( $dockerEnvPath, $phpVersion, $latestWpVersion, $previousWpVersion );

		putenv( 'DOCKER_BUILDKIT=1' );
		putenv( 'MSYS_NO_PATHCONV=1' );
		putenv( 'COMPOSE_PROJECT_NAME=shield-tests' );

		$composeArgs = [
			'docker',
			'compose',
			'-f',
			'tests/docker/docker-compose.yml',
		];

		$overallExitCode = 0;

		$runCompose = static function ( array $subCommand ) use ( $runner, $rootDir, $composeArgs ) :int {
			$command = array_merge( $composeArgs, $subCommand );
			$process = $runner->run( $command, $rootDir );
			return $process->getExitCode() ?? 1;
		};

		$runComposeIgnoringFailure = static function ( array $subCommand ) use ( $runner, $rootDir, $composeArgs ) :void {
			$command = array_merge( $composeArgs, $subCommand );
			$runner->run(
				$command,
				$rootDir,
				static function ( string $type, string $buffer ) :void {
					if ( $type === \Symfony\Component\Process\Process::ERR ) {
						fwrite( STDERR, $buffer );
					}
					else {
						echo $buffer;
					}
				}
			);
		};

		try {
			fwrite( STDOUT, 'Starting source-runtime Docker checks on working tree.'.PHP_EOL );
			$runComposeIgnoringFailure( [ 'down', '-v', '--remove-orphans' ] );

			if ( $runCompose( [ 'up', '-d', 'mysql-latest', 'mysql-previous' ] ) !== 0 ) {
				return 1;
			}
			if ( $runCompose( [ 'build', 'test-runner-latest', 'test-runner-previous' ] ) !== 0 ) {
				return 1;
			}
			if ( runSourceSetupOnce( $runner, $rootDir, $composeArgs ) !== 0 ) {
				return 1;
			}

			if ( $runCompose( [ 'run', '--rm', '-e', 'SHIELD_SKIP_INNER_SETUP=1', 'test-runner-latest' ] ) !== 0 ) {
				$overallExitCode = 1;
			}
			if ( $runCompose( [ 'run', '--rm', '-e', 'SHIELD_SKIP_INNER_SETUP=1', 'test-runner-previous' ] ) !== 0 ) {
				$overallExitCode = 1;
			}

			return $overallExitCode;
		}
		finally {
			$runComposeIgnoringFailure( [ 'down', '-v', '--remove-orphans' ] );
			if ( is_file( $dockerEnvPath ) ) {
				unlink( $dockerEnvPath );
			}
		}
	}
	finally {
		if ( $hasOriginalShieldPackagePath ) {
			putenv( 'SHIELD_PACKAGE_PATH='.$originalShieldPackagePath );
		}
		else {
			putenv( 'SHIELD_PACKAGE_PATH' );
		}
	}
}

/**
 * @param string[] $composeArgs
 */
function runSourceSetupOnce( ProcessRunner $runner, string $rootDir, array $composeArgs ) :int {
	fwrite( STDOUT, 'Preparing source mode test setup once before runtime checks.'.PHP_EOL );

	$runCompose = static function ( array $subCommand ) use ( $runner, $rootDir, $composeArgs ) :int {
		$command = array_merge( $composeArgs, $subCommand );
		$process = $runner->run( $command, $rootDir );
		return $process->getExitCode() ?? 1;
	};

	if ( $runCompose( [ 'run', '--rm', '--no-deps', 'test-runner-latest', 'composer', 'install', '--no-interaction', '--no-cache' ] ) !== 0 ) {
		return 1;
	}
	if ( $runCompose( [ 'run', '--rm', '--no-deps', 'test-runner-latest', 'composer', 'build:config' ] ) !== 0 ) {
		return 1;
	}

	$nodeProcess = $runner->run(
		[
			'docker',
			'run',
			'--rm',
			'-v',
			$rootDir.':/app',
			'-v',
			'/app/node_modules',
			'-w',
			'/app',
			'node:20.10',
			'sh',
			'-c',
			'npm ci --no-audit --no-fund && npm run build',
		],
		$rootDir
	);

	return $nodeProcess->getExitCode() ?? 1;
}

function isDockerAvailable( ProcessRunner $runner, string $rootDir ) :bool {
	$process = $runner->run(
		[ 'docker', '--version' ],
		$rootDir,
		static function () :void {
		}
	);
	return ( $process->getExitCode() ?? 1 ) === 0;
}

function isDockerDaemonRunning( ProcessRunner $runner, string $rootDir ) :bool {
	$process = $runner->run(
		[ 'docker', 'info' ],
		$rootDir,
		static function () :void {
		}
	);
	return ( $process->getExitCode() ?? 1 ) === 0;
}

function readDefaultPhpFromMatrixConfig( string $rootDir ) :string {
	$matrixFile = Path::join( $rootDir, '.github', 'config', 'matrix.conf' );
	if ( !is_file( $matrixFile ) ) {
		return '8.2';
	}

	$content = (string)file_get_contents( $matrixFile );
	if ( preg_match( '/^DEFAULT_PHP="?([^"\r\n]+)"?/m', $content, $matches ) !== 1 ) {
		return '8.2';
	}

	$defaultPhp = trim( (string)( $matches[ 1 ] ?? '' ) );
	return $defaultPhp !== '' ? $defaultPhp : '8.2';
}

/**
 * @return array{string,string}
 */
function detectWordpressVersions(
	ProcessRunner $runner,
	string $rootDir,
	BashCommandResolver $bashCommandResolver
) :array {
	$command = [ $bashCommandResolver->resolve(), './.github/scripts/detect-wp-versions.sh' ];
	$output = '';
	$process = $runner->run(
		$command,
		$rootDir,
		static function ( string $type, string $buffer ) use ( &$output ) :void {
			$output .= $buffer;
			if ( $type === \Symfony\Component\Process\Process::ERR ) {
				fwrite( STDERR, $buffer );
			}
			else {
				echo $buffer;
			}
		}
	);

	if ( ( $process->getExitCode() ?? 1 ) !== 0 ) {
		throw new RuntimeException( 'WordPress version detection failed.' );
	}

	$latest = '';
	$previous = '';
	if ( preg_match( '/^LATEST_VERSION=([^\r\n]+)/m', $output, $latestMatch ) === 1 ) {
		$latest = trim( (string)( $latestMatch[ 1 ] ?? '' ) );
	}
	if ( preg_match( '/^PREVIOUS_VERSION=([^\r\n]+)/m', $output, $previousMatch ) === 1 ) {
		$previous = trim( (string)( $previousMatch[ 1 ] ?? '' ) );
	}

	if ( $latest === '' || $previous === '' ) {
		throw new RuntimeException( 'Could not parse LATEST_VERSION/PREVIOUS_VERSION from detect-wp-versions.sh output.' );
	}

	return [ $latest, $previous ];
}

function writeSourceDockerEnvFile( string $dockerEnvPath, string $phpVersion, string $latestWpVersion, string $previousWpVersion ) :void {
	$lines = [
		'PHP_VERSION='.$phpVersion,
		'WP_VERSION_LATEST='.$latestWpVersion,
		'WP_VERSION_PREVIOUS='.$previousWpVersion,
		'TEST_PHP_VERSION='.$phpVersion,
		'SHIELD_TEST_MODE=source',
	];

	foreach ( [ 'PHPUNIT_DEBUG', 'SHIELD_TEST_VERBOSE' ] as $optionalEnvVar ) {
		$value = getenv( $optionalEnvVar );
		if ( is_string( $value ) && $value !== '' ) {
			$lines[] = $optionalEnvVar.'='.$value;
		}
	}

	$contents = implode( PHP_EOL, $lines ).PHP_EOL;
	if ( file_put_contents( $dockerEnvPath, $contents ) === false ) {
		throw new RuntimeException( 'Failed to write Docker env file: '.$dockerEnvPath );
	}
}
