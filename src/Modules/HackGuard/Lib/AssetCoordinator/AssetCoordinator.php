<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\AssetCoordinator;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\{
	AssetTrustResolver,
	Retrieve
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Snapshots\StoreAction\{
	Build,
	CleanStale,
	Load,
	ScheduleBuildAll,
	TouchAll
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\AssetChange\Cleanup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

class AssetCoordinator {

	use ExecOnce;
	use PluginControllerConsumer;

	private const ASSET_DELAY = 60;
	private const BUILD_DELAY = 60;
	private const WPV_DELAY = 10;
	private const RETRY_DELAY = 60;
	private const MAX_ATTEMPTS = 3;

	private const LEGACY_AFS = 'afs_asset_change_cleanup';
	private const LEGACY_BUILD = 'ptg_build_snapshots';
	private const LEGACY_WPV = 'ondemand_scan_wpv';

	protected function run() {
		\add_filter( 'upgrader_post_install', [ $this, 'onUpgraderPostInstall' ], 10, 2 );
		\add_action( 'upgrader_process_complete', [ $this, 'onUpgraderProcessComplete' ], 10, 2 );
		\add_action( '_core_updated_successfully', [ $this, 'onCoreUpdated' ], 10, 1 );
		\add_action( 'deleted_plugin', [ $this, 'onDeletedPlugin' ], 10, 2 );
		\add_action( 'deleted_theme', [ $this, 'onDeletedTheme' ], 10, 2 );
		\add_action( self::con()->prefix( 'hourly_cron' ), [ $this, 'runSnapshotMaintenance' ], 10, 0 );
		\add_action( 'shield/scan_queue_completed', [ $this, 'onScanQueueCompleted' ], 10, 0 );
		\add_action( $this->cronHook(), [ $this, 'runDueWork' ], 10, 1 );

		$this->importLegacyCrons();
		$this->reconcileWakeup();
	}

	public function onScanQueueCompleted() :void {
		$this->discoverMissingSnapshots();
	}

	public function onUpgraderPostInstall( $response, $hookExtra ) {
		if ( \is_array( $hookExtra ) ) {
			$this->enqueueHookAsset( 'plugin', $hookExtra[ 'plugin' ] ?? null );
			$this->enqueueHookAsset( 'theme', $hookExtra[ 'theme' ] ?? null );
		}
		return $response;
	}

	public function onUpgraderProcessComplete( $upgrader, $hookExtra ) :void {
		unset( $upgrader );

		if ( \is_array( $hookExtra )
			 && ( $hookExtra[ 'action' ] ?? null ) === 'update'
			 && ( $hookExtra[ 'type' ] ?? null ) === 'plugin' ) {
			foreach ( \is_array( $hookExtra[ 'plugins' ] ?? null ) ? $hookExtra[ 'plugins' ] : [] as $plugin ) {
				$this->enqueueHookAsset( 'plugin', $plugin );
			}
		}

		if ( \is_array( $hookExtra )
			 && ( $hookExtra[ 'action' ] ?? null ) === 'update'
			 && ( $hookExtra[ 'type' ] ?? null ) === 'theme' ) {
			foreach ( \is_array( $hookExtra[ 'themes' ] ?? null ) ? $hookExtra[ 'themes' ] : [] as $theme ) {
				$this->enqueueHookAsset( 'theme', $theme );
			}
		}

		$this->enqueueWpv();
	}

	public function onCoreUpdated( $newVersion = null ) :void {
		unset( $newVersion );
		$this->enqueueAsset( 'core', 'core' );
	}

	public function onDeletedPlugin( $plugin = null, $deleted = false ) :void {
		if ( $deleted === true ) {
			$this->enqueueHookAsset( 'plugin', $plugin );
			$this->enqueueWpv();
		}
	}

	public function onDeletedTheme( $stylesheet = null, $deleted = false ) :void {
		if ( $deleted === true ) {
			$this->enqueueHookAsset( 'theme', $stylesheet );
		}
	}

	public function enqueueAsset( string $assetType, string $assetKey, int $delay = self::ASSET_DELAY ) :bool {
		[ $assetType, $assetKey ] = $this->normalizeAsset( $assetType, $assetKey );
		if ( $assetType === '' || $assetKey === '' ) {
			return false;
		}

		return $this->enqueueAssetRecord( $assetType, $assetKey, [
			'attempts' => 0,
			'due_at'   => $this->now() + \max( 0, $delay ),
		] );
	}

	public function enqueuePromotionFollowUp(
		string $assetType,
		string $assetKey,
		string $requiredPublishedVersion
	) :bool {
		[ $assetType, $assetKey ] = $this->normalizeAsset( $assetType, $assetKey );
		if ( !\in_array( $assetType, [ 'plugin', 'theme' ], true )
			 || $assetKey === ''
			 || \trim( $requiredPublishedVersion ) === ''
			 || \strpos( $requiredPublishedVersion, "\0" ) !== false ) {
			return false;
		}

		return $this->enqueueAssetRecord( $assetType, $assetKey, [
			'attempts'                   => 0,
			'due_at'                     => $this->now() + self::ASSET_DELAY,
			'required_published_version' => $requiredPublishedVersion,
		] );
	}

	public function enqueueWpv( int $delay = self::WPV_DELAY ) :bool {
		$state = $this->readState();
		$state[ 'wpv' ] = [
			'attempts' => 0,
			'due_at'   => $this->now() + \max( 0, $delay ),
		];
		if ( !$this->writeState( $state ) ) {
			return false;
		}

		$this->reconcileWakeup();
		return true;
	}

	/**
	 * @throws \RuntimeException
	 */
	public function hasRetryableAssetWork() :bool {
		$state = $this->normalizeState( $this->readPersistedStateForReadiness(), true );
		foreach ( $state[ 'assets' ] as $records ) {
			foreach ( $records as $record ) {
				if ( $record[ 'attempts' ] < self::MAX_ATTEMPTS && $record[ 'due_at' ] > 0 ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param array<int,mixed> $assets
	 * @return array{
	 *     plugin:array<string,array{version:string,comparison_eligible:bool}>,
	 *     theme:array<int|string,array{version:string,comparison_eligible:bool}>
	 * }
	 */
	public function prepareFullScanSnapshotEligibility( array $assets, callable $heartbeat ) :array {
		$eligibility = [
			'plugin' => [],
			'theme'  => [],
		];
		$records = [];
		$identityCounts = [];

		foreach ( $assets as $asset ) {
			try {
				$record = $this->fullScanInventoryRecord( $asset );
				$identityCounts[ $record[ 'identity' ] ] = ( $identityCounts[ $record[ 'identity' ] ] ?? 0 ) + 1;
				if ( $identityCounts[ $record[ 'identity' ] ] === 1 ) {
					$eligibility[ $record[ 'type' ] ][ $record[ 'key' ] ] = [
						'version'             => $record[ 'version' ],
						'comparison_eligible' => false,
					];
				}
				$records[] = $record;
			}
			catch ( \Throwable $e ) {
				$this->logFullScanSnapshotFailure( $asset, $e );
				$records[] = null;
			}
		}

		$canBuild = \is_main_network() && \is_main_site();
		foreach ( $records as $index => $record ) {
			try {
				if ( $record === null ) {
					continue;
				}
				if ( $identityCounts[ $record[ 'identity' ] ] !== 1 ) {
					throw new \UnexpectedValueException( 'Duplicate or conflicting snapshot identity.' );
				}

				$isUsable = $this->hasUsableSnapshot( $record[ 'asset' ] );
				if ( !$isUsable && $canBuild ) {
					$this->buildSnapshot( $record[ 'asset' ] );
					$isUsable = $this->hasUsableSnapshot( $record[ 'asset' ] );
					if ( $isUsable ) {
						Retrieve::resetMemoization();
						AssetTrustResolver::resetMemoization();
					}
					else {
						throw new \RuntimeException( 'Snapshot remained unusable after preparation.' );
					}
				}
				$eligibility[ $record[ 'type' ] ][ $record[ 'key' ] ][ 'comparison_eligible' ] = $isUsable;
			}
			catch ( \Throwable $e ) {
				$this->logFullScanSnapshotFailure( $record[ 'asset' ] ?? $assets[ $index ], $e );
			}
			finally {
				$heartbeat();
			}
		}

		return $eligibility;
	}

	public function runSnapshotMaintenance() :void {
		if ( $this->discoverMissingSnapshots() ) {
			( new CleanStale() )->execute();
		}
	}

	public function discoverMissingSnapshots() :bool {
		if ( !\is_main_network()
			 || !\is_main_site()
			 || self::con()->is_my_upgrade
			 || self::con()->plugin_deleting ) {
			return false;
		}

		try {
			$maintenance = ( new TouchAll() )->run();
		}
		catch ( \Throwable $e ) {
			error_log( 'Shield asset coordinator snapshot discovery failed: '.$e->getMessage() );
			return false;
		}

		if ( $maintenance[ 'has_unusable' ] || $maintenance[ 'has_due_promotions' ] ) {
			$state = $this->readState();
			if ( empty( $state[ 'build_missing_snapshots' ] ) ) {
				$state[ 'build_missing_snapshots' ] = true;
				if ( $this->writeState( $state ) ) {
					$this->reconcileWakeup();
				}
			}
		}
		if ( !$maintenance[ 'touches_succeeded' ] ) {
			error_log( 'Shield asset coordinator snapshot retention touch failed.' );
		}
		return $maintenance[ 'touches_succeeded' ];
	}

	public function runDueWork( $scheduledDueAt = null ) :void {
		unset( $scheduledDueAt );

		$state = $this->readState();
		$now = $this->now();
		$dueAssets = [];
		foreach ( $state[ 'assets' ] as $assetType => $records ) {
			foreach ( $records as $assetKey => $record ) {
				if ( $record[ 'attempts' ] < self::MAX_ATTEMPTS
					 && $record[ 'due_at' ] > 0
					 && $record[ 'due_at' ] <= $now ) {
					$dueAssets[] = [ $assetType, $assetKey, $record ];
				}
			}
		}
		$buildPending = \is_main_network()
						&& \is_main_site()
						&& !empty( $state[ 'build_missing_snapshots' ] );
		$wpvDue = isset( $state[ 'wpv' ] )
				  && $state[ 'wpv' ][ 'attempts' ] < self::MAX_ATTEMPTS
				  && $state[ 'wpv' ][ 'due_at' ] > 0
				  && $state[ 'wpv' ][ 'due_at' ] <= $now;

		foreach ( $dueAssets as [ $assetType, $assetKey, $record ] ) {
			$error = null;
			try {
				$cleanup = new Cleanup();
				$succeeded = isset( $record[ 'required_published_version' ] )
					? $cleanup->processPromotionFollowUp(
						$assetType,
						$assetKey,
						$record[ 'required_published_version' ]
					)
					: $cleanup->process( $assetType, $assetKey );
			}
			catch ( \Throwable $e ) {
				$succeeded = false;
				$error = $e;
			}
			$this->recordAssetResult( $assetType, $assetKey, $record, $succeeded, $error );
		}

		if ( $buildPending ) {
			try {
				( new ScheduleBuildAll() )->build();
			}
			catch ( \Throwable $e ) {
				error_log( 'Shield asset coordinator snapshot build failed: '.$e->getMessage() );
			}
			$this->clearBuildIntent();
		}

		if ( $wpvDue ) {
			$error = null;
			try {
				$succeeded = self::con()->comps->scans->startNewScans( [ 'wpv' ] )->hasStarted();
			}
			catch ( \Throwable $e ) {
				$succeeded = false;
				$error = $e;
			}
			$this->recordWpvResult( $succeeded, $error );
		}

		$this->reconcileWakeup();
	}

	public function reconcileWakeup() :void {
		$state = $this->readState();
		$now = $this->now();
		$nextDue = null;

		foreach ( $state[ 'assets' ] as $records ) {
			foreach ( $records as $record ) {
				if ( $record[ 'attempts' ] < self::MAX_ATTEMPTS && $record[ 'due_at' ] > 0 ) {
					$nextDue = $nextDue === null ? $record[ 'due_at' ] : \min( $nextDue, $record[ 'due_at' ] );
				}
			}
		}
		if ( isset( $state[ 'wpv' ] )
			 && $state[ 'wpv' ][ 'attempts' ] < self::MAX_ATTEMPTS
			 && $state[ 'wpv' ][ 'due_at' ] > 0 ) {
			$nextDue = $nextDue === null ? $state[ 'wpv' ][ 'due_at' ] : \min( $nextDue, $state[ 'wpv' ][ 'due_at' ] );
		}
		if ( \is_main_network()
			 && \is_main_site()
			 && !empty( $state[ 'build_missing_snapshots' ] ) ) {
			$buildDue = $now + self::BUILD_DELAY;
			$nextDue = $nextDue === null ? $buildDue : \min( $nextDue, $buildDue );
		}
		if ( $nextDue === null ) {
			return;
		}

		$nextDue = \max( $now + 1, $nextDue );
		foreach ( $this->cronInstances( $this->cronHook() ) as $event ) {
			if ( $event[ 'timestamp' ] <= $nextDue ) {
				return;
			}
		}

		if ( \wp_schedule_single_event( $nextDue, $this->cronHook(), [ $nextDue ] ) === false ) {
			error_log( 'Shield asset coordinator could not schedule its wakeup.' );
		}
	}

	public function deleteState() :void {
		if ( \is_multisite() ) {
			\delete_site_option( $this->optionKey() );
		}
		else {
			\delete_option( $this->optionKey() );
		}
	}

	private function enqueueHookAsset( string $assetType, $assetKey ) :bool {
		if ( !\is_string( $assetKey ) ) {
			return false;
		}
		$assetKey = \trim( $assetKey );
		return $assetKey !== '' && $assetKey !== '0'
			? $this->enqueueAsset( $assetType, $assetKey )
			: false;
	}

	private function enqueueAssetRecord( string $assetType, string $assetKey, array $record ) :bool {
		$state = $this->readState();
		$existing = $state[ 'assets' ][ $assetType ][ $assetKey ] ?? null;
		if ( \array_key_exists( 'required_published_version', $record )
				 && $existing !== null
				 && !\array_key_exists( 'required_published_version', $existing )
				 && $existing[ 'attempts' ] < self::MAX_ATTEMPTS
				 && $existing[ 'due_at' ] > 0 ) {
			$record = [
				'attempts' => 0,
				'due_at'   => \min( $existing[ 'due_at' ], $record[ 'due_at' ] ),
			];
		}
		$state[ 'assets' ][ $assetType ][ $assetKey ] = $record;
		if ( !$this->writeState( $state ) ) {
			return false;
		}

		$this->reconcileWakeup();
		return true;
	}

	private function recordAssetResult(
		string $assetType,
		string $assetKey,
		array $selectedRecord,
		bool $succeeded,
		?\Throwable $error
	) :void {
		$state = $this->readState();
		if ( ( $state[ 'assets' ][ $assetType ][ $assetKey ] ?? null ) !== $selectedRecord ) {
			return;
		}

		if ( $succeeded ) {
			unset( $state[ 'assets' ][ $assetType ][ $assetKey ] );
			if ( $this->writeState( $state ) ) {
				$this->signalNotificationReadinessOpened();
			}
			return;
		}

		$attempts = \min(
			self::MAX_ATTEMPTS,
			$selectedRecord[ 'attempts' ] + 1
		);
		$selectedRecord[ 'attempts' ] = $attempts;
		$selectedRecord[ 'due_at' ] = $attempts >= self::MAX_ATTEMPTS ? 0 : $this->now() + self::RETRY_DELAY;
		$state[ 'assets' ][ $assetType ][ $assetKey ] = $selectedRecord;
		if ( $this->writeState( $state ) && $attempts === self::MAX_ATTEMPTS ) {
			$this->logExhausted( $assetType, $assetKey, $error );
			$this->signalNotificationReadinessOpened();
		}
	}

	private function signalNotificationReadinessOpened() :void {
		try {
			if ( self::con()->comps->scans->isReadyForScanResultNotifications() ) {
				\do_action( ScansController::HOOK_SCAN_RESULT_NOTIFICATION_READINESS_OPENED );
			}
		}
		catch ( \Throwable $e ) {
			error_log( 'Shield scan-result notification readiness signal failed: '.\substr(
				(string)\preg_replace( '#\s+#', ' ', $e->getMessage() ),
				0,
				300
			) );
		}
	}

	private function recordWpvResult( bool $succeeded, ?\Throwable $error ) :void {
		$state = $this->readState();
		if ( !isset( $state[ 'wpv' ] ) ) {
			return;
		}

		if ( $succeeded ) {
			unset( $state[ 'wpv' ] );
			$this->writeState( $state );
			return;
		}

		$attempts = \min( self::MAX_ATTEMPTS, $state[ 'wpv' ][ 'attempts' ] + 1 );
		$state[ 'wpv' ] = [
			'attempts' => $attempts,
			'due_at'   => $attempts >= self::MAX_ATTEMPTS ? 0 : $this->now() + self::RETRY_DELAY,
		];
		if ( $this->writeState( $state ) && $attempts === self::MAX_ATTEMPTS ) {
			$this->logExhausted( 'wpv', '', $error );
		}
	}

	private function clearBuildIntent() :void {
		$state = $this->readState();
		if ( isset( $state[ 'build_missing_snapshots' ] ) ) {
			unset( $state[ 'build_missing_snapshots' ] );
			$this->writeState( $state );
		}
	}

	private function logExhausted( string $type, string $key, ?\Throwable $error ) :void {
		error_log( \sprintf(
			'Shield asset coordinator exhausted %s%s after %d attempts%s',
			$type,
			$key === '' ? '' : ':'.$key,
			self::MAX_ATTEMPTS,
			$error === null ? '.' : ': '.$error->getMessage()
		) );
	}

	private function importLegacyCrons() :void {
		$hooks = [
			self::LEGACY_AFS   => self::con()->prefix( self::LEGACY_AFS ),
			self::LEGACY_BUILD => self::con()->prefix( self::LEGACY_BUILD ),
			self::LEGACY_WPV   => self::con()->prefix( self::LEGACY_WPV ),
		];
		$state = $this->readState();
		$events = [];
		$importedAssets = [];
		$importedBuild = false;
		$importedWpvAt = null;

		foreach ( Services::WpCron()->getCrons() as $timestamp => $scheduledHooks ) {
			$timestamp = (int)$timestamp;
			foreach ( $hooks as $type => $hook ) {
				if ( $type === self::LEGACY_BUILD
					 && ( !\is_main_network() || !\is_main_site() ) ) {
					continue;
				}
				foreach ( (array)( $scheduledHooks[ $hook ] ?? [] ) as $instance ) {
					$args = \is_array( $instance[ 'args' ] ?? null ) ? $instance[ 'args' ] : [];
					if ( $type === self::LEGACY_AFS ) {
						$asset = $this->normalizeLegacyAsset( $args );
						if ( $asset === null ) {
							continue;
						}
						[ $assetType, $assetKey ] = $asset;
						$importedAssets[ $assetType ][ $assetKey ] = isset( $importedAssets[ $assetType ][ $assetKey ] )
							? \min( $importedAssets[ $assetType ][ $assetKey ], $timestamp )
							: $timestamp;
					}
					elseif ( $type === self::LEGACY_BUILD ) {
						$state[ 'build_missing_snapshots' ] = true;
						$importedBuild = true;
					}
					else {
						$importedWpvAt = $importedWpvAt === null ? $timestamp : \min( $importedWpvAt, $timestamp );
					}
					$events[] = [
						'timestamp' => $timestamp,
						'hook'      => $hook,
						'args'      => $args,
					];
				}
			}
		}

		if ( empty( $events ) ) {
			return;
		}

		foreach ( $importedAssets as $assetType => $assetRecords ) {
			foreach ( $assetRecords as $assetKey => $dueAt ) {
				$currentDueAt = $state[ 'assets' ][ $assetType ][ $assetKey ][ 'due_at' ] ?? 0;
				$state[ 'assets' ][ $assetType ][ $assetKey ] = [
					'attempts' => 0,
					'due_at'   => $currentDueAt > 0 ? \min( $currentDueAt, $dueAt ) : $dueAt,
				];
			}
		}
		if ( $importedWpvAt !== null ) {
			$currentDueAt = $state[ 'wpv' ][ 'due_at' ] ?? 0;
			$state[ 'wpv' ] = [
				'attempts' => 0,
				'due_at'   => $currentDueAt > 0 ? \min( $currentDueAt, $importedWpvAt ) : $importedWpvAt,
			];
		}
		if ( !$this->writeState( $state ) ) {
			return;
		}

		$stored = $this->readState();
		foreach ( $importedAssets as $assetType => $assetKeys ) {
			foreach ( \array_keys( $assetKeys ) as $assetKey ) {
				if ( !isset( $stored[ 'assets' ][ $assetType ][ $assetKey ] ) ) {
					error_log( 'Shield asset coordinator could not verify imported legacy work.' );
					return;
				}
			}
		}
		if ( ( $importedBuild && empty( $stored[ 'build_missing_snapshots' ] ) )
			 || ( $importedWpvAt !== null && !isset( $stored[ 'wpv' ] ) ) ) {
			error_log( 'Shield asset coordinator could not verify imported legacy work.' );
			return;
		}

		foreach ( $events as $event ) {
			\wp_unschedule_event( $event[ 'timestamp' ], $event[ 'hook' ], $event[ 'args' ] );
		}
	}

	private function normalizeLegacyAsset( array $args ) :?array {
		if ( !\is_string( $args[ 0 ] ?? null ) || !\is_string( $args[ 1 ] ?? null ) ) {
			return null;
		}
		[ $assetType, $assetKey ] = $this->normalizeAsset( $args[ 0 ], $args[ 1 ] );
		return $assetType === '' || $assetKey === '' ? null : [ $assetType, $assetKey ];
	}

	/**
	 * @return array{0:string,1:string}
	 */
	private function normalizeAsset( string $assetType, string $assetKey ) :array {
		$assetType = \in_array( $assetType, [ 'core', 'plugin', 'theme' ], true ) ? $assetType : '';
		$assetKey = $assetType === 'core' ? 'core' : \trim( $assetKey );
		if ( $assetKey === '0' ) {
			$assetKey = '';
		}
		return [ $assetType, $assetKey ];
	}

	private function readState() :array {
		return $this->normalizeState( $this->readStoredOption() );
	}

	private function readStoredOption() {
		return \is_multisite()
			? \get_site_option( $this->optionKey(), null )
			: \get_option( $this->optionKey(), null );
	}

	/**
	 * @throws \RuntimeException
	 */
	private function readPersistedStateForReadiness() :array {
		return $this->readRawPersistedState() ?? [];
	}

	/**
	 * @throws \RuntimeException
	 */
	private function readRawPersistedState() :?array {
		global $wpdb;

		if ( !\is_object( $wpdb ) ) {
			throw new \RuntimeException( 'Asset coordinator readiness state query failed.' );
		}

		if ( \is_multisite() ) {
			$table = (string)( $wpdb->sitemeta ?? '' );
			$query = $table === '' ? false : $wpdb->prepare(
				\sprintf(
					"SELECT `meta_value` AS `option_value` FROM `%s` WHERE `site_id`=%%d AND `meta_key`=%%s LIMIT 1;",
					$table
				),
				\get_current_network_id(),
				$this->optionKey()
			);
		}
		else {
			$table = (string)( $wpdb->options ?? '' );
			$query = $table === '' ? false : $wpdb->prepare(
				\sprintf(
					"SELECT `option_value` FROM `%s` WHERE `option_name`=%%s LIMIT 1;",
					$table
				),
				$this->optionKey()
			);
		}

		if ( !\is_string( $query ) || $query === '' ) {
			throw new \RuntimeException( 'Asset coordinator readiness state query failed.' );
		}

		try {
			$rows = Services::WpDb()->selectCustom( $query );
		}
		catch ( \Throwable $e ) {
			throw new \RuntimeException( 'Asset coordinator readiness state query failed.', 0, $e );
		}

		if ( !\is_array( $rows )
			 || (string)( $wpdb->last_error ?? '' ) !== '' ) {
			throw new \RuntimeException( 'Asset coordinator readiness state query failed.' );
		}
		if ( $rows === [] ) {
			return null;
		}
		if ( \count( $rows ) !== 1
			 || !\is_array( $rows[ 0 ] )
			 || !\array_key_exists( 'option_value', $rows[ 0 ] )
			 || !\is_string( $rows[ 0 ][ 'option_value' ] ) ) {
			throw new \RuntimeException( 'Asset coordinator readiness state is malformed.' );
		}

		$state = \maybe_unserialize( $rows[ 0 ][ 'option_value' ] );
		if ( !\is_array( $state ) ) {
			throw new \RuntimeException( 'Asset coordinator readiness state is malformed.' );
		}
		return $state;
	}

	private function writeState( array $state ) :bool {
		$state = $this->normalizeState( $state );
		$updated = \is_multisite()
			? \update_site_option( $this->optionKey(), $state )
			: \update_option( $this->optionKey(), $state, false );

		if ( $updated !== false ) {
			return true;
		}
		if ( $this->readStoredOption() === $state ) {
			return true;
		}
		try {
			if ( $this->readRawPersistedState() === $state ) {
				return true;
			}
		}
		catch ( \Throwable $e ) {
		}

		error_log( 'Shield asset coordinator state write failed.' );
		return false;
	}

	private function normalizeState( $raw, bool $strictAssets = false ) :array {
		$state = [
			'assets' => [
				'plugin' => [],
				'theme'  => [],
				'core'   => [],
			],
		];
		if ( !\is_array( $raw ) ) {
			if ( $strictAssets ) {
				throw new \RuntimeException( 'Asset coordinator readiness state is malformed.' );
			}
			return $state;
		}
		$rawAssets = $raw[ 'assets' ] ?? [];
		if ( !\is_array( $rawAssets ) ) {
			if ( $strictAssets ) {
				throw new \RuntimeException( 'Asset coordinator readiness assets are malformed.' );
			}
			$rawAssets = [];
		}
		if ( $strictAssets && !empty( \array_diff( \array_keys( $rawAssets ), [ 'plugin', 'theme', 'core' ] ) ) ) {
			throw new \RuntimeException( 'Asset coordinator readiness assets are malformed.' );
		}

		foreach ( [ 'plugin', 'theme', 'core' ] as $assetType ) {
			$records = $rawAssets[ $assetType ] ?? [];
			if ( !\is_array( $records ) ) {
				if ( $strictAssets ) {
					throw new \RuntimeException( 'Asset coordinator readiness assets are malformed.' );
				}
				$records = [];
			}
			foreach ( $records as $assetKey => $record ) {
				if ( !\is_string( $assetKey ) || !\is_array( $record ) ) {
					if ( $strictAssets ) {
						throw new \RuntimeException( 'Asset coordinator readiness asset record is malformed.' );
					}
					continue;
				}
				[ $normalizedType, $normalizedKey ] = $this->normalizeAsset( $assetType, $assetKey );
				if ( $normalizedType === '' || $normalizedKey === ''
					 || !\is_int( $record[ 'attempts' ] ?? null )
					 || !\is_int( $record[ 'due_at' ] ?? null )
					 || $record[ 'attempts' ] < 0
					 || $record[ 'due_at' ] < 0 ) {
					if ( $strictAssets ) {
						throw new \RuntimeException( 'Asset coordinator readiness asset record is malformed.' );
					}
					continue;
				}
				$hasRequiredPublishedVersion = \array_key_exists( 'required_published_version', $record );
				if ( $hasRequiredPublishedVersion
					 && ( !\in_array( $normalizedType, [ 'plugin', 'theme' ], true )
						  || !\is_string( $record[ 'required_published_version' ] )
						  || \trim( $record[ 'required_published_version' ] ) === ''
						  || \strpos( $record[ 'required_published_version' ], "\0" ) !== false ) ) {
					if ( $strictAssets ) {
						throw new \RuntimeException( 'Asset coordinator readiness asset record is malformed.' );
					}
					continue;
				}
				$attempts = \min( self::MAX_ATTEMPTS, $record[ 'attempts' ] );
				$normalizedRecord = [
					'attempts' => $attempts,
					'due_at'   => $attempts >= self::MAX_ATTEMPTS ? 0 : $record[ 'due_at' ],
				];
				if ( $hasRequiredPublishedVersion ) {
					$normalizedRecord[ 'required_published_version' ] = $record[ 'required_published_version' ];
				}
				$state[ 'assets' ][ $normalizedType ][ $normalizedKey ] = $normalizedRecord;
			}
		}

		if ( ( $raw[ 'build_missing_snapshots' ] ?? false ) === true ) {
			$state[ 'build_missing_snapshots' ] = true;
		}
		if ( \is_array( $raw[ 'wpv' ] ?? null )
			 && \is_int( $raw[ 'wpv' ][ 'attempts' ] ?? null )
			 && \is_int( $raw[ 'wpv' ][ 'due_at' ] ?? null )
			 && $raw[ 'wpv' ][ 'attempts' ] >= 0
			 && $raw[ 'wpv' ][ 'due_at' ] >= 0 ) {
			$attempts = \min( self::MAX_ATTEMPTS, $raw[ 'wpv' ][ 'attempts' ] );
			$state[ 'wpv' ] = [
				'attempts' => $attempts,
				'due_at'   => $attempts >= self::MAX_ATTEMPTS ? 0 : $raw[ 'wpv' ][ 'due_at' ],
			];
		}

		return $state;
	}

	private function cronInstances( string $hook ) :array {
		$instances = [];
		foreach ( Services::WpCron()->getCrons() as $timestamp => $scheduledHooks ) {
			foreach ( (array)( $scheduledHooks[ $hook ] ?? [] ) as $instance ) {
				$instances[] = [
					'timestamp' => (int)$timestamp,
					'args'      => \is_array( $instance[ 'args' ] ?? null ) ? $instance[ 'args' ] : [],
				];
			}
		}
		return $instances;
	}

	private function optionKey() :string {
		return self::con()->prefix( 'asset_coordinator_state' );
	}

	private function cronHook() :string {
		return self::con()->prefix( 'asset_coordinator' );
	}

	/**
	 * @param mixed $asset
	 * @return array{
	 *     asset:WpPluginVo|WpThemeVo,
	 *     type:string,
	 *     key:string,
	 *     version:string,
	 *     identity:string
	 * }
	 */
	private function fullScanInventoryRecord( $asset ) :array {
		if ( $asset instanceof WpPluginVo && $asset->asset_type === 'plugin' ) {
			$assetType = 'plugin';
			$assetKey = $asset->file;
		}
		elseif ( $asset instanceof WpThemeVo && $asset->asset_type === 'theme' ) {
			$assetType = 'theme';
			$assetKey = $asset->stylesheet;
		}
		else {
			throw new \UnexpectedValueException( 'Invalid full-scan snapshot asset type.' );
		}

		$assetVersion = $asset->version;
		if ( !\is_string( $assetKey )
			 || \trim( $assetKey ) === ''
			 || \strpos( $assetKey, "\0" ) !== false
			 || !\is_string( $assetVersion )
			 || \trim( $assetVersion ) === ''
			 || \strpos( $assetVersion, "\0" ) !== false ) {
			throw new \UnexpectedValueException( 'Invalid full-scan snapshot asset identity.' );
		}

		return [
			'asset'    => $asset,
			'type'     => $assetType,
			'key'      => $assetKey,
			'version'  => $assetVersion,
			'identity' => $assetType."\0".$assetKey,
		];
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 */
	protected function hasUsableSnapshot( $asset ) :bool {
		try {
			return ( new Load() )
				->setAsset( $asset )
				->run()
				->isUsable();
		}
		catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * @param WpPluginVo|WpThemeVo $asset
	 */
	protected function buildSnapshot( $asset ) :void {
		( new Build() )
			->setAsset( $asset )
			->run();
	}

	/**
	 * @param mixed $asset
	 */
	private function logFullScanSnapshotFailure( $asset, \Throwable $error ) :void {
		$assetType = $asset instanceof WpPluginVo
			? 'plugin'
			: ( $asset instanceof WpThemeVo ? 'theme' : 'unknown' );
		$assetKey = '';
		try {
			$key = $assetType === 'plugin'
				? $asset->file
				: ( $assetType === 'theme' ? $asset->stylesheet : '' );
			$assetKey = \is_string( $key ) ? $key : '';
		}
		catch ( \Throwable $e ) {
			unset( $e );
		}
		$assetKey = (string)\preg_replace( '#[^\x20-\x7e]#', '?', $assetKey );
		$assetKey = \substr( $assetKey, 0, 160 );

		error_log( \sprintf(
			'Shield full-scan snapshot preparation failed: type=%s key=%s message=%s',
			$assetType,
			$assetKey,
			\substr( (string)\preg_replace( '#\s+#', ' ', $error->getMessage() ), 0, 300 )
		) );
	}

	private function now() :int {
		return Services::Request()->ts();
	}
}
