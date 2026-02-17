#!/usr/bin/env php
<?php declare( strict_types=1 );

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
$runtimeRoot = normalizePath(
	(string)( $options['runtime-root'] ?? ( normalizePath( sys_get_temp_dir() ).'/shield-playground-runtime' ) )
);

if ( $runClean ) {
	exit( runCleanup( $runtimeRoot ) );
}

$runsRoot = pathJoin( $runtimeRoot, 'runs' );
ensureDirectory( $runsRoot );
$pruned = pruneRunDirectories( $runsRoot, $retentionDays, $maxRuns );

if ( !$runBlueprintOnly ) {
	exit( runInteractiveServer(
		$phpVersion,
		$wpVersion,
		$port,
		$projectRoot,
		$runtimeRoot,
		$pruned
	) );
}

exit( runSmokeCheck(
	$phpVersion,
	$wpVersion,
	$projectRoot,
	$runtimeRoot,
	$runsRoot,
	$pruned,
	$strictMode
) );

function runInteractiveServer(
	string $phpVersion,
	string $wpVersion,
	int $port,
	string $projectRoot,
	string $runtimeRoot,
	array $pruned
) :int {
	$pluginPathInVfs = '/wordpress/wp-content/plugins/wp-simple-firewall';
	$tempDir = pathJoin( $runtimeRoot, 'server-tmp-'.substr( bin2hex( random_bytes( 4 ) ), 0, 8 ) );
	ensureDirectory( $tempDir );
	putenv( 'TEMP='.$tempDir );
	putenv( 'TMP='.$tempDir );

	$blueprintPath = buildSmokeCheckBlueprint( $runtimeRoot, false );
	register_shutdown_function( static function () use ( $blueprintPath, $tempDir, $runtimeRoot ) :void {
		if ( is_file( $blueprintPath ) ) {
			@unlink( $blueprintPath );
		}
		if ( is_dir( $tempDir ) ) {
			removeDirectoryRecursive( $tempDir, $runtimeRoot );
		}
	} );

	$command = buildPlaygroundCommand(
		$projectRoot,
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

	return runPassthruCommand( $command );
}

function runSmokeCheck(
	string $phpVersion,
	string $wpVersion,
	string $projectRoot,
	string $runtimeRoot,
	string $runsRoot,
	array $pruned,
	bool $strictMode
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

	$blueprintPath = buildSmokeCheckBlueprint( $runDir, true );
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
	];

	$summary['preflight'] = [
		'runtime_root_writable' => isWritableDirectory( $runtimeRoot ) ? 'pass' : 'fail',
		'run_dir_writable' => isWritableDirectory( $runDir ) ? 'pass' : 'fail',
	];

	$localBinary = findLocalPlaygroundBinary( $projectRoot );
	$summary['preflight']['playground_cli_source'] = $localBinary !== null ? 'pass' : 'warn';
	if ( $localBinary === null ) {
		$summary['warnings'][] = 'Local @wp-playground/cli binary not found. Falling back to npx remote fetch.';
		$summary['warnings'][] = 'For deterministic local runs, install local CLI: npm install --save-dev @wp-playground/cli';
	}

	$wpOrgBlueprint = validateWpOrgBlueprint( $projectRoot );
	$summary['checks']['blueprint_schema_valid'] = $wpOrgBlueprint['ok'] ? 'pass' : 'fail';
	if ( !$wpOrgBlueprint['ok'] ) {
		$summary['errors'][] = $wpOrgBlueprint['message'];
	}

	$command = buildPlaygroundCommand(
		$projectRoot,
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
	$combinedOutput = trim( $commandResult['stdout']."\n".$commandResult['stderr'] );

	if ( stripos( $combinedOutput, 'Plugin /wordpress/wp-content/plugins/wp-simple-firewall activation printed the following bytes' ) !== false ) {
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
	}

	$requiredChecks = [
		'blueprint_schema_valid',
		'bootstrap_wordpress',
		'activate_plugin',
		'verify_plugin_active',
		'login_admin',
	];

	foreach ( $requiredChecks as $requiredCheck ) {
		if ( ( $summary['checks'][ $requiredCheck ] ?? '' ) !== 'pass' ) {
			$summary['errors'][] = sprintf( 'Required check failed or missing: %s', $requiredCheck );
		}
	}

	$environmentFailure = false;
	if ( $commandResult['exit_code'] !== 0 ) {
		if ( isEnvironmentFailureOutput( $combinedOutput ) ) {
			$environmentFailure = true;
			$summary['errors'][] = 'Environment failure while invoking Playground CLI.';
		}
		else {
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
	removeDirectoryRecursive( $runDir, $runsRoot );
	$summary['artifacts_cleaned'] = true;
	$summary['cleaned_bytes'] = $cleanupBytes;

	renderSummary( $summary, $combinedOutput );

	return (int)$summary['exit_code'];
}

function buildSmokeCheckBlueprint( string $baseDir, bool $isCheckMode ) :string {
	$blueprintPath = pathJoin( $baseDir, $isCheckMode ? 'blueprint.check.json' : 'blueprint.server.json' );

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
		'steps'   => [
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

function pruneRunDirectories( string $runsRoot, int $retentionDays, int $maxRuns ) :array {
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
		removeDirectoryRecursive( $dir, $runsRoot );
		$removedDirs++;
	}

	return [
		'removed_dirs' => $removedDirs,
		'removed_bytes' => $removedBytes,
	];
}

function removeDirectoryRecursive( string $dir, string $allowedBase ) :void {
	$realDir = realpath( $dir );
	$realBase = realpath( $allowedBase );
	if ( $realDir === false || $realBase === false ) {
		return;
	}
	$normDir = normalizePath( $realDir );
	$normBase = normalizePath( $realBase );
	if ( strpos( $normDir, $normBase.'/' ) !== 0 ) {
		throw new RuntimeException( sprintf( 'Refusing to remove directory outside allowed base. Dir: %s Base: %s', $normDir, $normBase ) );
	}

	$items = scandir( $realDir );
	if ( is_array( $items ) ) {
		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}
			$path = $realDir.DIRECTORY_SEPARATOR.$item;
			if ( is_dir( $path ) && !is_link( $path ) ) {
				removeDirectoryRecursive( $path, $allowedBase );
			}
			else {
				@unlink( $path );
			}
		}
	}
	@rmdir( $realDir );
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

function runCleanup( string $runtimeRoot ) :int {
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
	removeDirectoryRecursive( $realRuntimeRoot, dirname( $realRuntimeRoot ) );
	echo "Removed runtime root. Reclaimed: ".formatBytes( $reclaimed )."\n";
	return EXIT_PASS;
}

function buildBaseCommand( array $playgroundArgs ) :array {
	if ( \PHP_OS_FAMILY !== 'Windows' ) {
		return array_merge( [ 'npx' ], $playgroundArgs );
	}

	$programFiles = getenv( 'ProgramFiles' ) ?: 'C:/Program Files';
	$npxPs1 = normalizePath( $programFiles ).'/nodejs/npx.ps1';

	if ( is_file( $npxPs1 ) ) {
		return array_merge(
			[
				'powershell.exe',
				'-NoProfile',
				'-ExecutionPolicy',
				'Bypass',
				'-File',
				$npxPs1,
			],
			$playgroundArgs
		);
	}

	return array_merge( [ 'npx.cmd' ], $playgroundArgs );
}

function buildPlaygroundCommand( string $projectRoot, string $subcommand, array $args ) :array {
	$localBinary = findLocalPlaygroundBinary( $projectRoot );
	if ( $localBinary !== null ) {
		return array_merge( [ $localBinary, $subcommand ], $args );
	}

	return buildBaseCommand( array_merge( [ '@wp-playground/cli@latest', $subcommand ], $args ) );
}

function findLocalPlaygroundBinary( string $projectRoot ) :?string {
	$candidates = \PHP_OS_FAMILY === 'Windows' ? [
		pathJoin( $projectRoot, 'node_modules', '.bin', 'wp-playground.cmd' ),
		pathJoin( $projectRoot, 'node_modules', '.bin', 'wp-playground' ),
	] : [
		pathJoin( $projectRoot, 'node_modules', '.bin', 'wp-playground' ),
	];

	foreach ( $candidates as $candidate ) {
		if ( is_file( $candidate ) ) {
			return $candidate;
		}
	}
	return null;
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
TXT;
}
