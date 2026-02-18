#!/usr/bin/env php
<?php declare( strict_types=1 );

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\SafeDirectoryRemover;

/**
 * Run WordPress Playground against the current Git working copy under the real plugin slug.
 *
 * Defaults to interactive server mode:
 *   composer playground:local
 *
 * Non-interactive activation/login smoke test with explicit report:
 *   composer playground:local:check
 *
 * Cleanup old runtime artifacts:
 *   composer playground:local:clean
 */

const EXIT_PASS = 0;
const EXIT_FAIL = 1;
const EXIT_ENV = 2;

$autoloadPath = dirname( __DIR__ ).'/vendor/autoload.php';
if ( !is_file( $autoloadPath ) ) {
	fwrite( STDERR, "Dependencies are not installed. Run 'composer install' first.\n" );
	exit( EXIT_ENV );
}
require $autoloadPath;

$options = getopt( '', [
	'php::',
	'wp::',
	'port::',
	'run-blueprint',
	'clean',
	'strict',
	'retention-days::',
	'max-runs::',
	'runtime-root::',
	'help',
] );

if ( isset( $options['help'] ) ) {
	echo usage();
	exit( 0 );
}

$phpVersion = (string)( $options['php'] ?? '8.2' );
$wpVersion = (string)( $options['wp'] ?? 'latest' );
$port = isset( $options['port'] ) ? (int)$options['port'] : 9400;
$runBlueprintOnly = isset( $options['run-blueprint'] );
$runClean = isset( $options['clean'] );
$strictMode = isset( $options['strict'] );
$retentionDays = max( 0, (int)( $options['retention-days'] ?? 7 ) );
$maxRuns = max( 1, (int)( $options['max-runs'] ?? 10 ) );

$projectRoot = normalizePath( dirname( __DIR__ ) );
$safeRemover = new SafeDirectoryRemover( $projectRoot );
$runtimeRoot = normalizePath(
	(string)( $options['runtime-root'] ?? ( normalizePath( sys_get_temp_dir() ).'/shield-playground-runtime' ) )
);

if ( $runClean ) {
	exit( runCleanup( $runtimeRoot, $safeRemover ) );
}

$runsRoot = pathJoin( $runtimeRoot, 'runs' );
ensureDirectory( $runsRoot );
$pruned = pruneRunDirectories( $runsRoot, $retentionDays, $maxRuns, $safeRemover );
$localPlaygroundBinary = findLocalPlaygroundBinary( $projectRoot );

if ( !$runBlueprintOnly ) {
	exit( runInteractiveServer(
		$phpVersion,
		$wpVersion,
		$port,
		$projectRoot,
		$runtimeRoot,
		$pruned,
		$localPlaygroundBinary,
		$safeRemover
	) );
}

exit( runSmokeCheck(
	$phpVersion,
	$wpVersion,
	$projectRoot,
	$runtimeRoot,
	$runsRoot,
	$pruned,
	$strictMode,
	$localPlaygroundBinary,
	$safeRemover
) );

function runInteractiveServer(
	string $phpVersion,
	string $wpVersion,
	int $port,
	string $projectRoot,
	string $runtimeRoot,
	array $pruned,
	?string $localPlaygroundBinary,
	SafeDirectoryRemover $safeRemover
) :int {
	if ( $localPlaygroundBinary === null ) {
		fwrite( STDERR, "Local @wp-playground/cli binary not found.\n" );
		fwrite( STDERR, "Install it with: npm install --save-dev @wp-playground/cli\n" );
		return EXIT_ENV;
	}

	$pluginPathInVfs = '/wordpress/wp-content/plugins/wp-simple-firewall';
	$tempDir = pathJoin( $runtimeRoot, 'server-tmp-'.substr( bin2hex( random_bytes( 4 ) ), 0, 8 ) );
	ensureDirectory( $tempDir );
	putenv( 'TEMP='.$tempDir );
	putenv( 'TMP='.$tempDir );

	$runtimeProbe = probeRuntimeEnvironment(
		$localPlaygroundBinary,
		$phpVersion,
		$wpVersion,
		$projectRoot,
		$runtimeRoot,
		$pluginPathInVfs,
		$safeRemover
	);
	if ( !$runtimeProbe['ok'] ) {
		fwrite( STDERR, "Interactive startup blocked: ".$runtimeProbe['error']."\n" );
		fwrite(
			STDERR,
			sprintf(
				"Requested PHP: %s (major.minor %s) | Actual runtime PHP: %s (major.minor %s)\n",
				$runtimeProbe['requested_php'],
				$runtimeProbe['requested_php_major_minor'],
				$runtimeProbe['actual_php_version'],
				$runtimeProbe['actual_php_major_minor']
			)
		);
		if ( $runtimeProbe['output_tail'] !== '' ) {
			fwrite( STDERR, "Output Tail (last 30 lines):\n".$runtimeProbe['output_tail']."\n" );
		}
		return (int)$runtimeProbe['exit_code'];
	}

	$blueprintPath = buildServerBlueprint( $runtimeRoot, $phpVersion, $wpVersion );
	register_shutdown_function( static function () use ( $blueprintPath, $tempDir, $runtimeRoot, $safeRemover ) :void {
		if ( is_file( $blueprintPath ) ) {
			@unlink( $blueprintPath );
		}
		if ( is_dir( $tempDir ) ) {
			$safeRemover->removeSubdirectoryOf( $tempDir, $runtimeRoot );
		}
	} );

	$command = buildPlaygroundCommand(
		$localPlaygroundBinary,
		'server',
		[
			'--php',
			$phpVersion,
			'--wp',
			$wpVersion,
			'--mount-dir',
			$projectRoot,
			$pluginPathInVfs,
			'--blueprint',
			$blueprintPath,
			'--port',
			(string)$port,
		]
	);

	echo "Starting local Playground server for current repo plugin code.\n";
	echo "Runtime root: {$runtimeRoot}\n";
	echo "Mounted host path: {$projectRoot}\n";
	echo "Mounted plugin path: {$pluginPathInVfs}\n";
	echo "Open in browser: http://127.0.0.1:{$port}/wp-admin/\n";
	echo "Pruned stale runs: {$pruned['removed_dirs']} directories, ".formatBytes( $pruned['removed_bytes'] )."\n";
	echo "Cleanup command: composer playground:local:clean\n";
	echo sprintf(
		"Version Verification: requested PHP %s (major.minor %s), runtime PHP %s (major.minor %s)\n",
		$runtimeProbe['requested_php'],
		$runtimeProbe['requested_php_major_minor'],
		$runtimeProbe['actual_php_version'],
		$runtimeProbe['actual_php_major_minor']
	);

	return runPassthruCommand( $command );
}

function runSmokeCheck(
	string $phpVersion,
	string $wpVersion,
	string $projectRoot,
	string $runtimeRoot,
	string $runsRoot,
	array $pruned,
	bool $strictMode,
	?string $localPlaygroundBinary,
	SafeDirectoryRemover $safeRemover
) :int {
	$pluginPathInVfs = '/wordpress/wp-content/plugins/wp-simple-firewall';
	$runDir = createRunDirectory( $runsRoot );
	$captureDir = pathJoin( $runDir, 'capture' );
	$tempDir = pathJoin( $runDir, 'tmp' );
	$reportPath = pathJoin( $captureDir, 'report.json' );

	ensureDirectory( $captureDir );
	ensureDirectory( $tempDir );
	putenv( 'TEMP='.$tempDir );
	putenv( 'TMP='.$tempDir );

	$blueprintPath = buildSmokeCheckBlueprint( $runDir, true, $phpVersion, $wpVersion );
	register_shutdown_function( static function () use ( $blueprintPath ) :void {
		if ( is_file( $blueprintPath ) ) {
			@unlink( $blueprintPath );
		}
	} );

	$summary = [
		'started_at' => gmdate( 'c' ),
		'run_dir' => $runDir,
		'runtime_root' => $runtimeRoot,
		'preflight' => [],
		'checks' => [],
		'warnings' => [],
		'errors' => [],
		'pruned' => $pruned,
		'strict_mode' => $strictMode,
		'version_verification' => [
			'requested_php' => $phpVersion,
			'requested_php_major_minor' => normalizePhpMajorMinor( $phpVersion ),
			'actual_runtime_php' => '',
			'actual_runtime_php_major_minor' => '',
			'actual_runtime_wp' => '',
		],
	];

	$summary['preflight'] = [
		'runtime_root_writable' => isWritableDirectory( $runtimeRoot ) ? 'pass' : 'fail',
		'run_dir_writable' => isWritableDirectory( $runDir ) ? 'pass' : 'fail',
	];

	$summary['preflight']['playground_cli_source'] = $localPlaygroundBinary !== null ? 'pass' : 'fail';

	$wpOrgBlueprint = validateWpOrgBlueprint( $projectRoot );
	$summary['checks']['blueprint_schema_valid'] = $wpOrgBlueprint['ok'] ? 'pass' : 'fail';
	if ( !$wpOrgBlueprint['ok'] ) {
		$summary['errors'][] = $wpOrgBlueprint['message'];
	}

	$combinedOutput = '';
	$environmentFailure = false;
	$commandExecuted = false;
	$commandResult = [
		'exit_code' => 1,
		'stdout' => '',
		'stderr' => '',
	];

	if ( $localPlaygroundBinary === null ) {
		$environmentFailure = true;
		$summary['errors'][] = 'Local @wp-playground/cli binary not found.';
		$summary['errors'][] = 'Install it with: npm install --save-dev @wp-playground/cli';
	}
	else {
		$command = buildPlaygroundCommand(
			$localPlaygroundBinary,
			'run-blueprint',
			[
				'--php',
				$phpVersion,
				'--wp',
				$wpVersion,
				'--mount-dir',
				$projectRoot,
				$pluginPathInVfs,
				'--mount-dir',
				$captureDir,
				'/capture',
				'--blueprint',
				$blueprintPath,
			]
		);
		$commandResult = runCommandCapture( $command );
		$commandExecuted = true;
		$combinedOutput = trim( $commandResult['stdout']."\n".$commandResult['stderr'] );

		if ( $commandResult['exit_code'] !== 0 && isEnvironmentFailureOutput( $combinedOutput ) ) {
			$environmentFailure = true;
			$summary['errors'][] = 'Environment failure while invoking Playground CLI.';
		}
	}

	if ( $combinedOutput !== '' && stripos( $combinedOutput, 'Plugin /wordpress/wp-content/plugins/wp-simple-firewall activation printed the following bytes' ) !== false ) {
		$summary['warnings'][] = 'Playground reported activation output bytes.';
	}

	if ( $combinedOutput !== '' && stripos( $combinedOutput, 'deprecated' ) !== false ) {
		$summary['warnings'][] = 'Deprecated notices detected during run.';
	}

	$report = readJsonFile( $reportPath );
	if ( is_array( $report ) ) {
		foreach ( $report['checks'] ?? [] as $key => $status ) {
			if ( is_string( $key ) && is_string( $status ) ) {
				$summary['checks'][ $key ] = $status;
			}
		}
		foreach ( $report['warnings'] ?? [] as $warning ) {
			if ( is_string( $warning ) && $warning !== '' ) {
				$summary['warnings'][] = $warning;
			}
		}
		foreach ( $report['errors'] ?? [] as $error ) {
			if ( is_string( $error ) && $error !== '' ) {
				$summary['errors'][] = $error;
			}
		}
		if ( isset( $report['runtime'] ) && is_array( $report['runtime'] ) ) {
			$runtimePhpVersion = (string)( $report['runtime']['php_version'] ?? '' );
			$runtimePhpMajorMinor = (string)( $report['runtime']['php_major_minor'] ?? '' );
			$runtimeWpVersion = (string)( $report['runtime']['wp_version'] ?? '' );

			$summary['version_verification']['actual_runtime_php'] = $runtimePhpVersion;
			$summary['version_verification']['actual_runtime_php_major_minor'] = $runtimePhpMajorMinor;
			$summary['version_verification']['actual_runtime_wp'] = $runtimeWpVersion;

			$summary['checks']['runtime_probe'] = ( $runtimePhpMajorMinor !== '' ) ? 'pass' : 'fail';
		}
	}

	if ( !$commandExecuted || $commandResult['exit_code'] !== 0 ) {
		$summary['checks']['runtime_probe'] = 'skip';
		$summary['checks']['runtime_php_version_match'] = 'skip';
	}
	else {
		$requestedPhpMajorMinor = normalizePhpMajorMinor( $phpVersion );
		$actualRuntimePhpMajorMinor = (string)( $summary['version_verification']['actual_runtime_php_major_minor'] ?? '' );
		if ( $actualRuntimePhpMajorMinor === '' ) {
			$summary['checks']['runtime_php_version_match'] = 'fail';
			$summary['errors'][] = 'Runtime PHP probe did not provide php_major_minor.';
		}
		elseif ( $actualRuntimePhpMajorMinor !== $requestedPhpMajorMinor ) {
			$summary['checks']['runtime_php_version_match'] = 'fail';
			$summary['errors'][] = sprintf(
				'Runtime PHP mismatch: requested %s (major.minor %s), actual %s (major.minor %s).',
				$phpVersion,
				$requestedPhpMajorMinor,
				$summary['version_verification']['actual_runtime_php'] ?: '(unknown)',
				$actualRuntimePhpMajorMinor
			);
		}
		else {
			$summary['checks']['runtime_php_version_match'] = 'pass';
		}
	}

	$requiredChecks = [
		'blueprint_schema_valid',
		'runtime_probe',
		'runtime_php_version_match',
		'bootstrap_wordpress',
		'activate_plugin',
		'verify_plugin_active',
		'login_admin',
	];

	if ( $commandExecuted && $commandResult['exit_code'] === 0 && !$environmentFailure ) {
		foreach ( $requiredChecks as $requiredCheck ) {
			if ( ( $summary['checks'][ $requiredCheck ] ?? '' ) !== 'pass' ) {
				$summary['errors'][] = sprintf( 'Required check failed or missing: %s', $requiredCheck );
			}
		}
	}

	if ( $commandExecuted && $commandResult['exit_code'] !== 0 ) {
		if ( !$environmentFailure ) {
			$summary['errors'][] = sprintf( 'Playground command failed with exit code %d.', $commandResult['exit_code'] );
		}
	}

	if ( $strictMode && !empty( $summary['warnings'] ) ) {
		$summary['errors'][] = 'Strict mode enabled: warnings are treated as failures.';
	}

	$summary['warnings'] = array_values( array_unique( array_filter( $summary['warnings'] ) ) );
	$summary['errors'] = array_values( array_unique( array_filter( $summary['errors'] ) ) );
	$summary['ended_at'] = gmdate( 'c' );

	if ( !empty( $summary['errors'] ) ) {
		$summary['result'] = $environmentFailure ? 'ENVIRONMENT_FAILURE' : 'FAIL';
		$summary['exit_code'] = $environmentFailure ? EXIT_ENV : EXIT_FAIL;
	}
	else {
		$summary['result'] = 'PASS';
		$summary['exit_code'] = EXIT_PASS;
	}

	$cleanupBytes = directorySize( $runDir );
	try {
		$safeRemover->removeSubdirectoryOf( $runDir, $runsRoot );
		$summary['artifacts_cleaned'] = true;
	}
	catch ( Throwable $e ) {
		$summary['artifacts_cleaned'] = false;
		$summary['warnings'][] = 'Cleanup warning: '.$e->getMessage();
	}
	$summary['cleaned_bytes'] = $cleanupBytes;

	renderSummary( $summary, $combinedOutput );

	return (int)$summary['exit_code'];
}

function buildSmokeCheckBlueprint( string $baseDir, bool $isCheckMode, string $phpVersion, string $wpVersion ) :string {
	$blueprintPath = pathJoin( $baseDir, $isCheckMode ? 'blueprint.check.json' : 'blueprint.server.json' );

	$runtimeCode = wrapReportUpdateCode( <<<PHP
require '/wordpress/wp-load.php';
global \$wp_version;
\$report['runtime'] = [
	'php_version' => PHP_VERSION,
	'php_major_minor' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
	'wp_version' => (string)( \$wp_version ?? '' ),
];
\$report['checks']['runtime_probe'] = 'pass';
PHP
	);

	$bootstrapCode = wrapReportUpdateCode( <<<PHP
require '/wordpress/wp-load.php';
\$report['checks']['bootstrap_wordpress'] = 'pass';
PHP
	);

	$activateCode = wrapReportUpdateCode( <<<PHP
require '/wordpress/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';

\$plugin = 'wp-simple-firewall/icwp-wpsf.php';
\$output = '';
ob_start();
\$result = activate_plugin( \$plugin );
\$output = (string)ob_get_clean();
\$active = is_plugin_active( \$plugin );

if ( !\$active ) {
	\$report['checks']['activate_plugin'] = 'fail';
	\$report['errors'][] = 'Plugin is not active after activation call.';
	\$shouldFail = 'activate_plugin_failed_not_active';
}
else {
	\$report['checks']['activate_plugin'] = 'pass';
}

if ( \$output !== '' ) {
	\$report['warnings'][] = 'Activation emitted output bytes.';
	\$report['artifacts']['activation_output_excerpt'] = substr( trim( \$output ), 0, 600 );
}

if ( is_wp_error( \$result ) ) {
	\$code = (string)\$result->get_error_code();
	\$message = (string)\$result->get_error_message();
	if ( \$code !== 'unexpected_output' ) {
		\$report['checks']['activate_plugin'] = 'fail';
		\$report['errors'][] = 'Activation error: '.\$message;
		\$shouldFail = 'activate_plugin_failed:'.\$message;
	}
	else {
		\$report['warnings'][] = 'Activation returned unexpected_output while plugin remained active.';
	}
}
PHP
	);

	$verifyActiveCode = wrapReportUpdateCode( <<<PHP
require '/wordpress/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';

if ( !is_plugin_active( 'wp-simple-firewall/icwp-wpsf.php' ) ) {
	\$report['checks']['verify_plugin_active'] = 'fail';
	\$report['errors'][] = 'verify_plugin_active check failed.';
	\$shouldFail = 'verify_plugin_active_failed';
}
else {
	\$report['checks']['verify_plugin_active'] = 'pass';
}
PHP
	);

	$loginMarkerCode = wrapReportUpdateCode( <<<PHP
\$report['checks']['login_admin'] = 'pass';
PHP
	);

	$probeCode = wrapReportUpdateCode( <<<PHP
require '/wordpress/wp-load.php';
if ( class_exists( '\\\\FernleafSystems\\\\Wordpress\\\\Plugin\\\\Shield\\\\Controller\\\\Controller' ) ) {
	\$report['checks']['probe_admin_bootstrap'] = 'pass';
}
else {
	\$report['checks']['probe_admin_bootstrap'] = 'warn';
	\$report['warnings'][] = 'Controller class was not available during probe.';
}
PHP
	);

	$blueprint = [
		'$schema' => 'https://playground.wordpress.net/blueprint-schema.json',
		'preferredVersions' => buildPreferredVersions( $phpVersion, $wpVersion ),
		'steps'   => [
			[
				'step' => 'runPHP',
				'code' => $runtimeCode,
			],
			[
				'step' => 'runPHP',
				'code' => $bootstrapCode,
			],
			[
				'step' => 'runPHP',
				'code' => $activateCode,
			],
			[
				'step' => 'runPHP',
				'code' => $verifyActiveCode,
			],
			[
				'step'     => 'login',
				'username' => 'admin',
				'password' => 'password',
			],
			[
				'step' => 'runPHP',
				'code' => $loginMarkerCode,
			],
			[
				'step' => 'runPHP',
				'code' => $probeCode,
			],
		],
	];

	$json = json_encode( $blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	if ( $json === false || file_put_contents( $blueprintPath, $json ) === false ) {
		fwrite( STDERR, "Failed to write blueprint file: {$blueprintPath}\n" );
		exit( 1 );
	}
	return $blueprintPath;
}

function buildServerBlueprint( string $baseDir, string $phpVersion, string $wpVersion ) :string {
	$blueprintPath = pathJoin( $baseDir, 'blueprint.server.json' );
	$activationCode = <<<'PHP'
<?php
require '/wordpress/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
$result = activate_plugin('wp-simple-firewall/icwp-wpsf.php');
if ( is_wp_error( $result ) ) {
	$code = (string)$result->get_error_code();
	if ( $code !== 'unexpected_output' ) {
		throw new Exception( 'Activation failed: '.$result->get_error_message() );
	}
}
PHP;

	$blueprint = [
		'$schema' => 'https://playground.wordpress.net/blueprint-schema.json',
		'preferredVersions' => buildPreferredVersions( $phpVersion, $wpVersion ),
		'steps' => [
			[
				'step' => 'runPHP',
				'code' => $activationCode,
			],
			[
				'step' => 'login',
				'username' => 'admin',
				'password' => 'password',
			],
		],
	];

	$json = json_encode( $blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	if ( $json === false || file_put_contents( $blueprintPath, $json ) === false ) {
		fwrite( STDERR, "Failed to write server blueprint file: {$blueprintPath}\n" );
		exit( EXIT_ENV );
	}
	return $blueprintPath;
}

function buildRuntimeProbeBlueprint( string $baseDir, string $phpVersion, string $wpVersion ) :string {
	$blueprintPath = pathJoin( $baseDir, 'blueprint.runtime-probe.json' );
	$probeCode = <<<'PHP'
<?php
require '/wordpress/wp-load.php';
global $wp_version;
$report = [
	'runtime' => [
		'php_version' => PHP_VERSION,
		'php_major_minor' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
		'wp_version' => (string)( $wp_version ?? '' ),
	],
];
if ( file_put_contents('/capture/report.json', json_encode($report)) === false ) {
	throw new Exception('Failed to write runtime probe report');
}
PHP;

	$blueprint = [
		'$schema' => 'https://playground.wordpress.net/blueprint-schema.json',
		'preferredVersions' => buildPreferredVersions( $phpVersion, $wpVersion ),
		'steps' => [
			[
				'step' => 'runPHP',
				'code' => $probeCode,
			],
		],
	];

	$json = json_encode( $blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	if ( $json === false || file_put_contents( $blueprintPath, $json ) === false ) {
		fwrite( STDERR, "Failed to write runtime probe blueprint file: {$blueprintPath}\n" );
		exit( EXIT_ENV );
	}
	return $blueprintPath;
}

function wrapReportUpdateCode( string $innerCode ) :string {
	$base = <<<'PHP'
<?php
$reportPath = '/capture/report.json';
$raw = is_file($reportPath) ? file_get_contents($reportPath) : '';
$report = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
if (!is_array($report)) {
	$report = [];
}
if (!isset($report['checks']) || !is_array($report['checks'])) {
	$report['checks'] = [];
}
if (!isset($report['warnings']) || !is_array($report['warnings'])) {
	$report['warnings'] = [];
}
if (!isset($report['errors']) || !is_array($report['errors'])) {
	$report['errors'] = [];
}
if (!isset($report['artifacts']) || !is_array($report['artifacts'])) {
	$report['artifacts'] = [];
}
$shouldFail = '';
__INNER_CODE__
file_put_contents($reportPath, json_encode($report));
if ($shouldFail !== '') {
	throw new Exception($shouldFail);
}
PHP;
	return str_replace( '__INNER_CODE__', trim( $innerCode ), $base );
}

function buildPreferredVersions( string $phpVersion, string $wpVersion ) :array {
	return [
		'php' => $phpVersion,
		'wp' => $wpVersion,
	];
}

function runPassthruCommand( array $command ) :int {
	$commandString = implode(
		' ',
		array_map(
			static fn( string $arg ) :string => escapeshellarg( $arg ),
			$command
		)
	);
	passthru( $commandString, $exitCode );
	return (int)$exitCode;
}

function runCommandCapture( array $command ) :array {
	$descriptorSpec = [
		0 => [ 'pipe', 'r' ],
		1 => [ 'pipe', 'w' ],
		2 => [ 'pipe', 'w' ],
	];
	$commandString = implode(
		' ',
		array_map(
			static fn( string $arg ) :string => escapeshellarg( $arg ),
			$command
		)
	);
	$process = proc_open( $commandString, $descriptorSpec, $pipes );
	if ( !is_resource( $process ) ) {
		return [
			'exit_code' => 1,
			'stdout' => '',
			'stderr' => 'Failed to start process.',
		];
	}

	fclose( $pipes[0] );
	$stdout = stream_get_contents( $pipes[1] );
	$stderr = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );

	$exitCode = proc_close( $process );

	return [
		'exit_code' => (int)$exitCode,
		'stdout' => is_string( $stdout ) ? $stdout : '',
		'stderr' => is_string( $stderr ) ? $stderr : '',
	];
}

function normalizePath( string $path ) :string {
	$normalized = str_replace( '\\', '/', $path );
	return rtrim( $normalized, '/' );
}

function normalizePhpMajorMinor( string $phpVersion ) :string {
	$trimmed = trim( $phpVersion );
	if ( preg_match( '/^(\d+)\.(\d+)/', $trimmed, $matches ) === 1 ) {
		return $matches[1].'.'.$matches[2];
	}
	return $trimmed;
}

function pathJoin( string ...$parts ) :string {
	$pieces = [];
	foreach ( $parts as $part ) {
		$part = trim( str_replace( '\\', '/', $part ) );
		if ( $part !== '' ) {
			$pieces[] = trim( $part, '/' );
		}
	}
	if ( empty( $pieces ) ) {
		return '';
	}
	$prefix = strpos( $parts[0], '/' ) === 0 ? '/' : '';
	return $prefix.implode( '/', $pieces );
}

function ensureDirectory( string $dir ) :void {
	if ( !is_dir( $dir ) && !mkdir( $dir, 0775, true ) && !is_dir( $dir ) ) {
		fwrite( STDERR, "Failed to create directory: {$dir}\n" );
		exit( EXIT_ENV );
	}
}

function createRunDirectory( string $runsRoot ) :string {
	$runId = sprintf( 'run-%s-%s', gmdate( 'Ymd-His' ), substr( bin2hex( random_bytes( 4 ) ), 0, 8 ) );
	$runDir = pathJoin( $runsRoot, $runId );
	ensureDirectory( $runDir );
	return $runDir;
}

function pruneRunDirectories(
	string $runsRoot,
	int $retentionDays,
	int $maxRuns,
	SafeDirectoryRemover $safeRemover
) :array {
	if ( !is_dir( $runsRoot ) ) {
		return [ 'removed_dirs' => 0, 'removed_bytes' => 0 ];
	}

	$entries = scandir( $runsRoot );
	if ( !is_array( $entries ) ) {
		return [ 'removed_dirs' => 0, 'removed_bytes' => 0 ];
	}

	$dirs = [];
	foreach ( $entries as $entry ) {
		if ( $entry === '.' || $entry === '..' ) {
			continue;
		}
		$path = pathJoin( $runsRoot, $entry );
		if ( is_dir( $path ) ) {
			$dirs[] = [
				'path' => $path,
				'mtime' => filemtime( $path ) ?: 0,
			];
		}
	}

	usort( $dirs, static function ( array $a, array $b ) :int {
		return $b['mtime'] <=> $a['mtime'];
	} );

	$toDelete = [];
	$cutoff = $retentionDays > 0 ? ( time() - ( $retentionDays * 86400 ) ) : 0;

	foreach ( $dirs as $idx => $dir ) {
		$deleteForCount = $idx >= $maxRuns;
		$deleteForAge = $cutoff > 0 && $dir['mtime'] > 0 && $dir['mtime'] < $cutoff;
		if ( $deleteForCount || $deleteForAge ) {
			$toDelete[ $dir['path'] ] = true;
		}
	}

	$removedDirs = 0;
	$removedBytes = 0;
	foreach ( array_keys( $toDelete ) as $dir ) {
		$removedBytes += directorySize( $dir );
		$safeRemover->removeSubdirectoryOf( $dir, $runsRoot );
		$removedDirs++;
	}

	return [
		'removed_dirs' => $removedDirs,
		'removed_bytes' => $removedBytes,
	];
}

function directorySize( string $dir ) :int {
	if ( !is_dir( $dir ) ) {
		return 0;
	}
	$size = 0;
	$items = scandir( $dir );
	if ( !is_array( $items ) ) {
		return 0;
	}
	foreach ( $items as $item ) {
		if ( $item === '.' || $item === '..' ) {
			continue;
		}
		$path = $dir.DIRECTORY_SEPARATOR.$item;
		if ( is_dir( $path ) && !is_link( $path ) ) {
			$size += directorySize( $path );
		}
		elseif ( is_file( $path ) ) {
			$size += filesize( $path ) ?: 0;
		}
	}
	return $size;
}

function formatBytes( int $bytes ) :string {
	$units = [ 'B', 'KB', 'MB', 'GB' ];
	$size = (float)max( 0, $bytes );
	$unit = 0;
	while ( $size >= 1024 && $unit < count( $units ) - 1 ) {
		$size /= 1024;
		$unit++;
	}
	return sprintf( '%.2f %s', $size, $units[ $unit ] );
}

function isWritableDirectory( string $dir ) :bool {
	return is_dir( $dir ) && is_writable( $dir );
}

function validateWpOrgBlueprint( string $projectRoot ) :array {
	$path = pathJoin( $projectRoot, 'infrastructure', 'wordpress-org', 'blueprints', 'blueprint.json' );
	if ( !is_file( $path ) ) {
		return [ 'ok' => false, 'message' => "WordPress.org blueprint file missing: {$path}" ];
	}
	$raw = file_get_contents( $path );
	if ( !is_string( $raw ) || $raw === '' ) {
		return [ 'ok' => false, 'message' => "WordPress.org blueprint file is unreadable: {$path}" ];
	}
	$decoded = json_decode( $raw, true );
	if ( !is_array( $decoded ) ) {
		return [ 'ok' => false, 'message' => "WordPress.org blueprint JSON is invalid: {$path}" ];
	}
	$steps = $decoded['steps'] ?? null;
	if ( !is_array( $steps ) || empty( $steps ) ) {
		return [ 'ok' => false, 'message' => 'WordPress.org blueprint has no steps.' ];
	}
	$hasInstallPlugin = false;
	foreach ( $steps as $step ) {
		if ( is_array( $step ) && ( $step['step'] ?? '' ) === 'installPlugin' ) {
			$hasInstallPlugin = true;
			break;
		}
	}
	if ( !$hasInstallPlugin ) {
		return [ 'ok' => false, 'message' => 'WordPress.org blueprint missing installPlugin step.' ];
	}
	return [ 'ok' => true, 'message' => 'WordPress.org blueprint validated.' ];
}

function readJsonFile( string $path ) :?array {
	if ( !is_file( $path ) ) {
		return null;
	}
	$raw = file_get_contents( $path );
	if ( !is_string( $raw ) || $raw === '' ) {
		return null;
	}
	$decoded = json_decode( $raw, true );
	return is_array( $decoded ) ? $decoded : null;
}

function isEnvironmentFailureOutput( string $output ) :bool {
	$needles = [
		'npm error code EACCES',
		'FetchError',
		'fetch failed',
		'connect EACCES',
		'spawn EPERM',
		'Could not determine Node.js install directory',
		'Cannot find module',
		'not recognized as an internal or external command',
		'request to https://registry.npmjs.org',
	];
	$lower = strtolower( $output );
	foreach ( $needles as $needle ) {
		if ( strpos( $lower, strtolower( $needle ) ) !== false ) {
			return true;
		}
	}
	return false;
}

function renderSummary( array $summary, string $combinedOutput ) :void {
	echo "=== Shield Playground Local Check ===\n";
	echo "Run Directory: {$summary['run_dir']}\n";
	echo "Runtime Root: {$summary['runtime_root']}\n";
	echo "Strict Mode: ".( $summary['strict_mode'] ? 'yes' : 'no' )."\n";

	echo "\nVersion Verification:\n";
	echo "  requested_php: {$summary['version_verification']['requested_php']}\n";
	echo "  requested_php_major_minor: {$summary['version_verification']['requested_php_major_minor']}\n";
	echo "  actual_runtime_php: ".( $summary['version_verification']['actual_runtime_php'] ?: '(unknown)' )."\n";
	echo "  actual_runtime_php_major_minor: ".( $summary['version_verification']['actual_runtime_php_major_minor'] ?: '(unknown)' )."\n";
	echo "  actual_runtime_wp: ".( $summary['version_verification']['actual_runtime_wp'] ?: '(unknown)' )."\n";

	echo "\nPreflight:\n";
	foreach ( $summary['preflight'] as $name => $status ) {
		echo sprintf( "  [%s] %s\n", strtoupper( (string)$status ), $name );
	}

	echo "\nChecks:\n";
	ksort( $summary['checks'] );
	foreach ( $summary['checks'] as $name => $status ) {
		echo sprintf( "  [%s] %s\n", strtoupper( (string)$status ), (string)$name );
	}

	echo "\nWarnings:\n";
	if ( empty( $summary['warnings'] ) ) {
		echo "  (none)\n";
	}
	else {
		foreach ( $summary['warnings'] as $warning ) {
			echo "  - {$warning}\n";
		}
	}

	echo "\nErrors:\n";
	if ( empty( $summary['errors'] ) ) {
		echo "  (none)\n";
	}
	else {
		foreach ( $summary['errors'] as $error ) {
			echo "  - {$error}\n";
		}
	}

	echo "\nArtifacts:\n";
	echo "  - run artifacts were removed after execution (".formatBytes( (int)$summary['cleaned_bytes'] ).")\n";
	echo sprintf(
		"  - pruned: %d directories, %s reclaimed\n",
		(int)$summary['pruned']['removed_dirs'],
		formatBytes( (int)$summary['pruned']['removed_bytes'] )
	);

	if ( !empty( $summary['errors'] ) && $combinedOutput !== '' ) {
		$tail = implode( "\n", array_slice( preg_split( '/\r\n|\r|\n/', $combinedOutput ) ?: [], -30 ) );
		echo "\nOutput Tail (last 30 lines):\n";
		echo $tail."\n";
	}

	echo "\nResult: {$summary['result']} (exit {$summary['exit_code']})\n";
}

function runCleanup( string $runtimeRoot, SafeDirectoryRemover $safeRemover ) :int {
	echo "Cleaning Playground runtime artifacts...\n";
	echo "Runtime root: {$runtimeRoot}\n";
	if ( !is_dir( $runtimeRoot ) ) {
		echo "Nothing to clean.\n";
		return EXIT_PASS;
	}
	$realRuntimeRoot = realpath( $runtimeRoot );
	if ( $realRuntimeRoot === false ) {
		echo "Nothing to clean.\n";
		return EXIT_PASS;
	}
	$normalized = normalizePath( $realRuntimeRoot );
	$expectedPrefix = normalizePath( sys_get_temp_dir() );
	if ( strpos( $normalized, $expectedPrefix.'/' ) !== 0 || strpos( $normalized, '/shield-playground-runtime' ) === false ) {
		fwrite( STDERR, "Refusing to clean unsafe runtime root path: {$normalized}\n" );
		return EXIT_ENV;
	}
	$reclaimed = directorySize( $realRuntimeRoot );
	$safeRemover->removeTempDirectory( $realRuntimeRoot );
	echo "Removed runtime root. Reclaimed: ".formatBytes( $reclaimed )."\n";
	return EXIT_PASS;
}

function buildPlaygroundCommand( string $localPlaygroundBinary, string $subcommand, array $args ) :array {
	return array_merge( [ $localPlaygroundBinary, $subcommand ], $args );
}

function findLocalPlaygroundBinary( string $projectRoot ) :?string {
	$candidates = \PHP_OS_FAMILY === 'Windows' ? [
		pathJoin( $projectRoot, 'node_modules', '.bin', 'wp-playground-cli.cmd' ),
		pathJoin( $projectRoot, 'node_modules', '.bin', 'wp-playground-cli' ),
		pathJoin( $projectRoot, 'node_modules', '.bin', 'wp-playground.cmd' ),
		pathJoin( $projectRoot, 'node_modules', '.bin', 'wp-playground' ),
	] : [
		pathJoin( $projectRoot, 'node_modules', '.bin', 'wp-playground-cli' ),
		pathJoin( $projectRoot, 'node_modules', '.bin', 'wp-playground' ),
	];

	foreach ( $candidates as $candidate ) {
		if ( is_file( $candidate ) ) {
			return $candidate;
		}
	}
	return null;
}

function probeRuntimeEnvironment(
	string $localPlaygroundBinary,
	string $phpVersion,
	string $wpVersion,
	string $projectRoot,
	string $runtimeRoot,
	string $pluginPathInVfs,
	SafeDirectoryRemover $safeRemover
) :array {
	$probeDir = pathJoin( $runtimeRoot, 'probe-'.substr( bin2hex( random_bytes( 4 ) ), 0, 8 ) );
	$captureDir = pathJoin( $probeDir, 'capture' );
	$tempDir = pathJoin( $probeDir, 'tmp' );
	$reportPath = pathJoin( $captureDir, 'report.json' );

	ensureDirectory( $captureDir );
	ensureDirectory( $tempDir );
	putenv( 'TEMP='.$tempDir );
	putenv( 'TMP='.$tempDir );

	$blueprintPath = buildRuntimeProbeBlueprint( $probeDir, $phpVersion, $wpVersion );
	try {
		$command = buildPlaygroundCommand(
			$localPlaygroundBinary,
			'run-blueprint',
			[
				'--php',
				$phpVersion,
				'--wp',
				$wpVersion,
				'--mount-dir',
				$projectRoot,
				$pluginPathInVfs,
				'--mount-dir',
				$captureDir,
				'/capture',
				'--blueprint',
				$blueprintPath,
			]
		);
		$result = runCommandCapture( $command );
		$combinedOutput = trim( $result['stdout']."\n".$result['stderr'] );

		$report = readJsonFile( $reportPath );
		$runtime = is_array( $report['runtime'] ?? null ) ? $report['runtime'] : [];
		$actualPhpVersion = (string)( $runtime['php_version'] ?? '' );
		$actualPhpMajorMinor = (string)( $runtime['php_major_minor'] ?? '' );
		$actualWpVersion = (string)( $runtime['wp_version'] ?? '' );
		$requestedPhpMajorMinor = normalizePhpMajorMinor( $phpVersion );

		if ( $result['exit_code'] !== 0 ) {
			$isEnvFailure = isEnvironmentFailureOutput( $combinedOutput );
			return [
				'ok' => false,
				'exit_code' => $isEnvFailure ? EXIT_ENV : EXIT_FAIL,
				'requested_php' => $phpVersion,
				'requested_php_major_minor' => $requestedPhpMajorMinor,
				'actual_php_version' => $actualPhpVersion,
				'actual_php_major_minor' => $actualPhpMajorMinor,
				'actual_wp_version' => $actualWpVersion,
				'error' => $isEnvFailure
					? 'Environment failure while running runtime probe.'
					: sprintf( 'Runtime probe failed with exit code %d.', $result['exit_code'] ),
				'output_tail' => tailOutput( $combinedOutput ),
			];
		}

		if ( $actualPhpMajorMinor === '' ) {
			return [
				'ok' => false,
				'exit_code' => EXIT_FAIL,
				'requested_php' => $phpVersion,
				'requested_php_major_minor' => $requestedPhpMajorMinor,
				'actual_php_version' => $actualPhpVersion,
				'actual_php_major_minor' => $actualPhpMajorMinor,
				'actual_wp_version' => $actualWpVersion,
				'error' => 'Runtime probe did not return php_major_minor.',
				'output_tail' => tailOutput( $combinedOutput ),
			];
		}

		if ( $actualPhpMajorMinor !== $requestedPhpMajorMinor ) {
			return [
				'ok' => false,
				'exit_code' => EXIT_FAIL,
				'requested_php' => $phpVersion,
				'requested_php_major_minor' => $requestedPhpMajorMinor,
				'actual_php_version' => $actualPhpVersion,
				'actual_php_major_minor' => $actualPhpMajorMinor,
				'actual_wp_version' => $actualWpVersion,
				'error' => 'Runtime PHP mismatch detected.',
				'output_tail' => tailOutput( $combinedOutput ),
			];
		}

		return [
			'ok' => true,
			'exit_code' => EXIT_PASS,
			'requested_php' => $phpVersion,
			'requested_php_major_minor' => $requestedPhpMajorMinor,
			'actual_php_version' => $actualPhpVersion,
			'actual_php_major_minor' => $actualPhpMajorMinor,
			'actual_wp_version' => $actualWpVersion,
			'error' => '',
			'output_tail' => '',
		];
	}
	finally {
		if ( is_file( $blueprintPath ) ) {
			@unlink( $blueprintPath );
		}
		if ( is_dir( $probeDir ) ) {
			$safeRemover->removeSubdirectoryOf( $probeDir, $runtimeRoot );
		}
	}
}

function tailOutput( string $output, int $lines = 30 ) :string {
	if ( trim( $output ) === '' ) {
		return '';
	}
	$parts = preg_split( '/\r\n|\r|\n/', $output ) ?: [];
	return implode( "\n", array_slice( $parts, -$lines ) );
}

function usage() :string {
	return <<<TXT
Usage:
  php bin/run-playground-local.php [--run-blueprint] [--clean] [--php=<version>] [--wp=<version>] [--port=<port>]
                                   [--retention-days=<days>] [--max-runs=<count>] [--runtime-root=<path>] [--strict]

Modes:
  default         Start local Playground server for browser testing.
  --run-blueprint Run non-interactive local smoke check and exit.
  --clean         Remove local Playground runtime artifacts created by this script.

Options:
  --php             PHP version for Playground. Default: 8.2
  --wp              WordPress version for Playground. Default: latest
  --port            Local server port (server mode only). Default: 9400
  --retention-days  Retain run artifacts for N days. Default: 7
  --max-runs        Retain at most N run directories. Default: 10
  --runtime-root    Override runtime root directory. Default: <system-temp>/shield-playground-runtime
  --strict          Treat warnings as failures for check mode.
  --help            Show this help.

Examples:
  composer playground:local
  composer playground:local:check
  composer playground:local:clean
  composer playground:local -- --php=8.3 --port=9500
  composer playground:local:check -- --strict

Notes:
  Local local-run workflows require node_modules/.bin/wp-playground.
  Install once: npm install --save-dev @wp-playground/cli
TXT;
}
