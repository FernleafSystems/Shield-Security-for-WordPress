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
		\add_action( $this->cronHook(), [ $this, 'runDueWork' ], 10, 1 );

		$this->importLegacyCrons();
		$this->reconcileWakeup();
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

		$state = $this->readState();
		$state[ 'assets' ][ $assetType ][ $assetKey ] = [
			'attempts' => 0,
			'due_at'   => $this->now() + \max( 0, $delay ),
		];
		if ( !$this->writeState( $state ) ) {
			return false;
		}

		$this->reconcileWakeup();
		return true;
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
				$records[] = $record;
			}
			catch ( \Throwable $e ) {
				$this->logFullScanSnapshotFailure( $asset, $e );
				$records[] = null;
			}
		}

		foreach ( $records as $record ) {
			if ( $record !== null && $identityCounts[ $record[ 'identity' ] ] === 1 ) {
				$eligibility[ $record[ 'type' ] ][ $record[ 'key' ] ] = [
					'version'             => $record[ 'version' ],
					'comparison_eligible' => false,
				];
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

		if ( $maintenance[ 'has_unusable' ] ) {
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
					$dueAssets[] = [ $assetType, $assetKey ];
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

		foreach ( $dueAssets as [ $assetType, $assetKey ] ) {
			$error = null;
			try {
				$succeeded = ( new Cleanup() )->process( $assetType, $assetKey );
			}
			catch ( \Throwable $e ) {
				$succeeded = false;
				$error = $e;
			}
			$this->recordAssetResult( $assetType, $assetKey, $succeeded, $error );
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

	private function recordAssetResult(
		string $assetType,
		string $assetKey,
		bool $succeeded,
		?\Throwable $error
	) :void {
		$state = $this->readState();
		if ( !isset( $state[ 'assets' ][ $assetType ][ $assetKey ] ) ) {
			return;
		}

		if ( $succeeded ) {
			unset( $state[ 'assets' ][ $assetType ][ $assetKey ] );
			$this->writeState( $state );
			return;
		}

		$attempts = \min(
			self::MAX_ATTEMPTS,
			$state[ 'assets' ][ $assetType ][ $assetKey ][ 'attempts' ] + 1
		);
		$state[ 'assets' ][ $assetType ][ $assetKey ] = [
			'attempts' => $attempts,
			'due_at'   => $attempts >= self::MAX_ATTEMPTS ? 0 : $this->now() + self::RETRY_DELAY,
		];
		if ( $this->writeState( $state ) && $attempts === self::MAX_ATTEMPTS ) {
			$this->logExhausted( $assetType, $assetKey, $error );
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

	private function writeState( array $state ) :bool {
		$state = $this->normalizeState( $state );
		$updated = \is_multisite()
			? \update_site_option( $this->optionKey(), $state )
			: \update_option( $this->optionKey(), $state, false );

		if ( $updated !== false || $this->readStoredOption() === $state ) {
			return true;
		}

		error_log( 'Shield asset coordinator state write failed.' );
		return false;
	}

	private function normalizeState( $raw ) :array {
		$state = [
			'assets' => [
				'plugin' => [],
				'theme'  => [],
				'core'   => [],
			],
		];
		if ( !\is_array( $raw ) ) {
			return $state;
		}

		foreach ( [ 'plugin', 'theme', 'core' ] as $assetType ) {
			foreach ( \is_array( $raw[ 'assets' ][ $assetType ] ?? null )
				? $raw[ 'assets' ][ $assetType ]
				: [] as $assetKey => $record ) {
				if ( !\is_string( $assetKey ) || !\is_array( $record ) ) {
					continue;
				}
				[ $normalizedType, $normalizedKey ] = $this->normalizeAsset( $assetType, $assetKey );
				if ( $normalizedType === '' || $normalizedKey === ''
					 || !\is_int( $record[ 'attempts' ] ?? null )
					 || !\is_int( $record[ 'due_at' ] ?? null )
					 || $record[ 'attempts' ] < 0
					 || $record[ 'due_at' ] < 0 ) {
					continue;
				}
				$attempts = \min( self::MAX_ATTEMPTS, $record[ 'attempts' ] );
				$state[ 'assets' ][ $normalizedType ][ $normalizedKey ] = [
					'attempts' => $attempts,
					'due_at'   => $attempts >= self::MAX_ATTEMPTS ? 0 : $record[ 'due_at' ],
				];
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
