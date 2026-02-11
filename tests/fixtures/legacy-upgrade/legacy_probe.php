<?php declare( strict_types=1 );

$options = \getopt( '', [
	'plugin-root:',
	'scenario::',
] );

$pluginRoot = \is_string( $options[ 'plugin-root' ] ?? null ) ? \trim( (string)$options[ 'plugin-root' ] ) : '';
$scenario = \is_string( $options[ 'scenario' ] ?? null ) ? \trim( (string)$options[ 'scenario' ] ) : 'guards';

$result = [
	'ok'         => true,
	'scenario'   => $scenario,
	'pluginRoot' => $pluginRoot,
	'legacyRoot' => '',
	'checks'     => [],
	'errors'     => [],
];

if ( $pluginRoot === '' ) {
	$result[ 'ok' ] = false;
	$result[ 'errors' ][] = 'Missing required --plugin-root option.';
	echo \json_encode( $result, \JSON_UNESCAPED_SLASHES ).\PHP_EOL;
	exit( 0 );
}

$normalisePath = static function ( string $path ) :string {
	return \str_replace( '\\', '/', $path );
};

$legacyRoot = \rtrim( $normalisePath( $pluginRoot ), '/' ).'/src/lib/src';
$result[ 'legacyRoot' ] = $legacyRoot;

$shieldPrefix = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\';
\spl_autoload_register(
	static function ( string $className ) use ( $shieldPrefix, $legacyRoot ) :void {
		if ( \strpos( $className, $shieldPrefix ) !== 0 ) {
			return;
		}
		$relative = \substr( $className, \strlen( $shieldPrefix ) );
		$path = $legacyRoot.'/'.\str_replace( '\\', '/', $relative ).'.php';
		if ( \file_exists( $path ) ) {
			require_once $path;
		}
	},
	true,
	true
);

$assertClassLoadsFromLegacy = static function ( string $className ) use ( $normalisePath ) :string {
	$ref = new \ReflectionClass( $className );
	$filePath = $normalisePath( (string)$ref->getFileName() );
	if ( \strpos( $filePath, '/src/lib/src/' ) === false ) {
		throw new \RuntimeException( \sprintf( 'Class did not load from legacy path: %s (%s)', $className, $filePath ) );
	}
	return $filePath;
};

if ( $scenario === 'precheck' ) {
	$classesToRemainMissing = [
		'FernleafSystems\\Wordpress\\Plugin\\Shield\\Controller\\Dependencies\\Monolog',
		'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\HackGuard\\Lib\\Snapshots\\FindAssetsToSnap',
		'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\IPs\\Components\\ProcessOffense',
		'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\IPs\\Lib\\Bots\\BotSignalsRecord',
		'FernleafSystems\\Wordpress\\Plugin\\Shield\\DBs\\Event\\Ops\\Handler',
		'FernleafSystems\\Wordpress\\Plugin\\Shield\\DBs\\CrowdSecSignals\\Ops\\Handler',
		'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\AuditTrail\\Lib\\Snapshots\\Ops\\Delete',
		'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\AuditTrail\\Lib\\Snapshots\\Ops\\Store',
	];

	foreach ( $classesToRemainMissing as $className ) {
		try {
			if ( \class_exists( $className, false ) ) {
				throw new \RuntimeException( 'Class was already loaded before probe: '.$className );
			}

			$found = \class_exists( $className, true );
			if ( $found ) {
				$filePath = ( new \ReflectionClass( $className ) )->getFileName();
				throw new \RuntimeException( \sprintf( 'Class unexpectedly autoloaded before duplication: %s (%s)', $className, $filePath ) );
			}

			$result[ 'checks' ][ $className ] = [
				'ok'    => true,
				'found' => false,
			];
		}
		catch ( \Throwable $e ) {
			$result[ 'ok' ] = false;
			$result[ 'checks' ][ $className ] = [
				'ok'    => false,
				'error' => $e->getMessage(),
			];
		}
	}

	echo \json_encode( $result, \JSON_UNESCAPED_SLASHES ).\PHP_EOL;
	exit( 0 );
}

$checks = [
	'monolog_guard' => static function () use ( $assertClassLoadsFromLegacy ) :array {
		$className = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\Controller\\Dependencies\\Monolog';
		$filePath = $assertClassLoadsFromLegacy( $className );
		$instance = new $className();

		try {
			$instance->assess();
			throw new \RuntimeException( 'Monolog::assess() did not throw legacy shutdown guard exception.' );
		}
		catch ( \Exception $e ) {
			if ( $e->getMessage() !== 'Legacy shutdown guard: monolog disabled.' ) {
				throw new \RuntimeException( 'Unexpected monolog guard exception message: '.$e->getMessage() );
			}
			return [
				'class'    => $className,
				'file'     => $filePath,
				'message'  => $e->getMessage(),
				'threw'    => true,
			];
		}
	},
	'find_assets_guard' => static function () use ( $assertClassLoadsFromLegacy ) :array {
		$className = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\HackGuard\\Lib\\Snapshots\\FindAssetsToSnap';
		$filePath = $assertClassLoadsFromLegacy( $className );
		$instance = new $className();
		$result = $instance->run();
		if ( $result !== [] ) {
			throw new \RuntimeException( 'FindAssetsToSnap::run() did not return empty array in legacy guard.' );
		}
		return [
			'class' => $className,
			'file'  => $filePath,
		];
	},
	'process_offense_guard' => static function () use ( $assertClassLoadsFromLegacy ) :array {
		$className = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\IPs\\Components\\ProcessOffense';
		$filePath = $assertClassLoadsFromLegacy( $className );
		$instance = new $className();

		if ( !\method_exists( $instance, 'setIp' ) ) {
			throw new \RuntimeException( 'Legacy ProcessOffense missing setIp().' );
		}

		if ( !\method_exists( $instance, 'incrementOffenses' ) ) {
			throw new \RuntimeException( 'Legacy ProcessOffense missing incrementOffenses().' );
		}

		$instance->setIp( '203.0.113.10' );
		$instance->execute();
		$instance->incrementOffenses( 2, true, true );

		return [
			'class' => $className,
			'file'  => $filePath,
		];
	},
	'bot_signals_guard' => static function () use ( $assertClassLoadsFromLegacy ) :array {
		$className = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\IPs\\Lib\\Bots\\BotSignalsRecord';
		$filePath = $assertClassLoadsFromLegacy( $className );
		$instance = new $className();

		$instance->setIP( '203.0.113.20' );
		$record = $instance->retrieve();
		if ( !$record instanceof \FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\BotSignalRecord ) {
			throw new \RuntimeException( 'Legacy BotSignalsRecord::retrieve() did not return BotSignalRecord.' );
		}

		if ( (int)$record->notbot_at !== 0 ) {
			throw new \RuntimeException( 'Legacy BotSignalsRecord::retrieve()->notbot_at must be 0.' );
		}

		$updated = $instance->updateSignalField( 'blocked_at', 123 );
		if ( (int)$updated->blocked_at !== 123 ) {
			throw new \RuntimeException( 'Legacy BotSignalsRecord::updateSignalField() did not set expected timestamp.' );
		}

		if ( !$instance->store( $record ) ) {
			throw new \RuntimeException( 'Legacy BotSignalsRecord::store() did not return true.' );
		}

		if ( !$instance->delete() ) {
			throw new \RuntimeException( 'Legacy BotSignalsRecord::delete() did not return true.' );
		}

		$recordFile = $assertClassLoadsFromLegacy( \FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\BotSignalRecord::class );

		return [
			'class'      => $className,
			'file'       => $filePath,
			'recordFile' => $recordFile,
			'notbot_at'  => (int)$record->notbot_at,
		];
	},
	'event_db_handler_guard' => static function () use ( $assertClassLoadsFromLegacy ) :array {
		$className = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\DBs\\Event\\Ops\\Handler';
		$filePath = $assertClassLoadsFromLegacy( $className );
		$instance = new $className( [] );

		if ( !\property_exists( $instance, 'use_table_ready_cache' ) ) {
			throw new \RuntimeException( 'Legacy Event handler missing use_table_ready_cache property.' );
		}

		$instance->use_table_ready_cache = true;
		$instance->execute();
		$isReady = $instance->isReady();
		if ( $isReady ) {
			throw new \RuntimeException( 'Legacy Event handler must report not-ready to suppress commit path.' );
		}

		$instance->commitEvents( [ 'test_evt' => 1 ] );
		$commitEventResult = $instance->commitEvent( 'test_evt', 1 );
		if ( $commitEventResult !== false ) {
			throw new \RuntimeException( 'Legacy Event handler commitEvent() must return false.' );
		}

		return [
			'class'    => $className,
			'file'     => $filePath,
			'isReady'  => $isReady,
			'committed' => $commitEventResult,
		];
	},
	'crowdsec_signals_db_handler_guard' => static function () use ( $assertClassLoadsFromLegacy ) :array {
		$className = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\DBs\\CrowdSecSignals\\Ops\\Handler';
		$filePath = $assertClassLoadsFromLegacy( $className );
		$instance = new $className( [] );

		if ( !\property_exists( $instance, 'use_table_ready_cache' ) ) {
			throw new \RuntimeException( 'Legacy CrowdSecSignals handler missing use_table_ready_cache property.' );
		}
		$instance->use_table_ready_cache = true;
		$instance->execute();

		if ( !\method_exists( $instance, 'getRecord' )
			 || !\method_exists( $instance, 'getQueryInserter' )
			 || !\method_exists( $instance, 'getQuerySelector' ) ) {
			throw new \RuntimeException( 'Legacy CrowdSecSignals handler missing required methods.' );
		}

		$record = $instance->getRecord()->applyFromArray( [
			'scenario' => 'legacy-test',
			'scope'    => 'ip',
			'value'    => '203.0.113.33',
			'milli_at' => '123456',
		] );
		$wrapped = $record->arrayDataWrap( [
			'context' => [
				'method' => 'GET',
			],
		] );
		if ( empty( $wrapped ) ) {
			throw new \RuntimeException( 'Legacy CrowdSecSignals record arrayDataWrap() must return non-empty metadata.' );
		}

		$record->meta = $wrapped;
		$inserted = $instance->getQueryInserter()->insert( $record );
		if ( $inserted !== true ) {
			throw new \RuntimeException( 'Legacy CrowdSecSignals inserter must return true.' );
		}

		$count = $instance->getQuerySelector()->count();
		if ( $count !== 0 ) {
			throw new \RuntimeException( 'Legacy CrowdSecSignals selector count must be 0.' );
		}

		return [
			'class'    => $className,
			'file'     => $filePath,
			'inserted' => $inserted,
			'count'    => $count,
		];
	},
	'snapshot_delete_guard' => static function () use ( $assertClassLoadsFromLegacy ) :array {
		$className = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\AuditTrail\\Lib\\Snapshots\\Ops\\Delete';
		$filePath = $assertClassLoadsFromLegacy( $className );
		$instance = new $className();
		$deleted = $instance->delete( 'legacy-probe' );
		if ( $deleted !== false ) {
			throw new \RuntimeException( 'Legacy Snapshot Delete::delete() must return false.' );
		}

		return [
			'class'   => $className,
			'file'    => $filePath,
			'deleted' => $deleted,
		];
	},
	'snapshot_store_guard' => static function () use ( $assertClassLoadsFromLegacy ) :array {
		$className = 'FernleafSystems\\Wordpress\\Plugin\\Shield\\Modules\\AuditTrail\\Lib\\Snapshots\\Ops\\Store';
		$filePath = $assertClassLoadsFromLegacy( $className );
		$instance = new $className();
		$snapshot = new \stdClass();
		$snapshot->slug = 'legacy-probe';
		$snapshot->data = [ 'source' => 'legacy_probe' ];
		$snapshot->snapshot_at = 1234567890;
		$stored = $instance->store( $snapshot );
		if ( $stored !== false ) {
			throw new \RuntimeException( 'Legacy Snapshot Store::store() must return false.' );
		}

		return [
			'class'  => $className,
			'file'   => $filePath,
			'stored' => $stored,
		];
	},
];

foreach ( $checks as $name => $check ) {
	try {
		$result[ 'checks' ][ $name ] = [
			'ok'      => true,
			'details' => $check(),
		];
	}
	catch ( \Throwable $e ) {
		$result[ 'ok' ] = false;
		$result[ 'checks' ][ $name ] = [
			'ok'    => false,
			'error' => $e->getMessage(),
		];
		$result[ 'errors' ][] = \sprintf( '%s: %s', $name, $e->getMessage() );
	}
}

echo \json_encode( $result, \JSON_UNESCAPED_SLASHES ).\PHP_EOL;
exit( 0 );
