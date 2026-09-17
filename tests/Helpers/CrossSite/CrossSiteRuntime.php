<?php
// WP-CLI eval-file wraps helpers before execution, so this file cannot declare strict_types first.

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	PluginImportExport_Export,
	PluginImportExport_HandshakeConfirm,
	PluginImportExport_UpdateNotified
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Handler as ImportExportProfilesDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Record as ImportExportProfileRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Handler as ImportExportSitesDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Record as ImportExportSiteRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\{
	Export,
	Import
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Diagnostics\{
	HttpOutcome,
	ObservationStore,
	SyncObservation
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\ProfileOptionsCatalog;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\ProfileRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\QueueScheduler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\ScopedTargetHostRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SyncSiteUrlValidator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\WhitelistNotifyQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Services\Services;

require_once dirname( __DIR__ ).'/RuntimeTestState.php';

$crossSiteRuntimeArgs = isset( $args ) && \is_array( $args ) ? $args : [];
$crossSiteAction = (string)( $crossSiteRuntimeArgs[ 0 ] ?? '' );
$crossSitePayload = [];
if ( isset( $crossSiteRuntimeArgs[ 1 ] ) ) {
	$decodedPayload = \json_decode( (string)\base64_decode( (string)$crossSiteRuntimeArgs[ 1 ], true ), true );
	$crossSitePayload = \is_array( $decodedPayload ) ? $decodedPayload : [];
}

try {
	$crossSiteRuntime = new class() {

		private const LOCAL_STATE_EXCEPTIONS = [
			'importexport_masterurl',
		];
		private const RUNTIME_INVARIANT_KEYS = [
			'global_enable_plugin_features',
			'importexport_enable',
		];

		/**
		 * @param array<string,mixed> $payload
		 * @return array<string,mixed>
		 */
		public function run( string $action, array $payload ) :array {
			switch ( $action ) {
			case 'setup':
					return $this->setup( (string)( $payload[ 'role' ] ?? '' ) );
				case 'secret':
					return [
						'secret' => RuntimeTestState::controller()->comps->import_export->getImportExportSecretKey(),
					];
				case 'state':
					return $this->state();
				case 'apply-corpus':
					return $this->applyCorpus();
				case 'run-notify-hook':
					return $this->runNotifyHook();
				case 'queue-state':
					return $this->queueState();
				case 'advance-queue-event':
					return $this->advanceQueueEvent();
				case 'group-c-setup':
					return $this->groupCSetup( (string)( $payload[ 'mode' ] ?? '' ) );
				case 'group-c-state':
					return $this->groupCState( (string)( $payload[ 'mode' ] ?? '' ) );
				case 'group-c-recover':
					return $this->groupCRecover();
				case 'legacy-migration-check':
					return $this->legacyMigrationCheck( $payload );
				case 'migration-state':
					return $this->migrationState();
				case 'cron-state':
					return $this->cronState();
				case 'export-options':
					return $this->exportOptions();
				case 'b2-prepare-master-row':
					return $this->b2PrepareMasterRow();
				case 'b2-prepare-client':
					return $this->b2PrepareClient();
				case 'b2-prepare-retry-after-cooldown':
					return $this->b2PrepareRetryAfterCooldown();
				case 'b2-reset-callback-observer':
					return $this->b2ResetCallbackObserver();
				case 'b2-snapshot':
					return $this->b2Snapshot(
						(string)( $payload[ 'role' ] ?? '' ),
						(string)( $payload[ 'expected_url' ] ?? '' )
					);
				case 'b2-pull':
					return $this->b2Pull();
				case 'b2-expired-export-request':
					return $this->b2ExpiredExportRequest();
				default:
					throw new \RuntimeException( 'Unknown cross-site runtime action: '.$action );
			}
		}

		/**
		 * @return array<string,mixed>
		 */
		private function setup( string $role ) :array {
			RuntimeTestState::applyPremiumCapabilities( $this->requiredCapabilities() );
			RuntimeTestState::ensureDb( [ 'file_locker', ImportExportProfilesDB::DB_KEY, ImportExportSitesDB::DB_KEY ] );
			RuntimeTestState::primeShieldNetHandshake();
			$this->clearImportExportRuntimeState();

			$con = RuntimeTestState::controller();
			$con->opts
				->optSet( 'global_enable_plugin_features', 'Y' )
				->optSet( 'importexport_enable', 'Y' )
				->optSet( 'importexport_masterurl', '' )
				->optSet( 'importexport_whitelist', [] )
				->optSet( 'import_url_ids', [] )
				->optSet( 'xfer_excluded', [] )
				->store();
			$this->primeCorpusBaselines();

			return [
				'role' => $role,
				'capabilities' => $this->requiredCapabilities(),
				'notify_hook' => $this->notifyHook(),
				'import_hook' => $this->importHook(),
				'queue_hook' => $this->queueHook(),
			];
		}

		/**
		 * @return array<string,mixed>
		 */
		private function state() :array {
			$con = RuntimeTestState::controller();
			return [
				'home_url' => Services::WpGeneral()->getHomeUrl(),
				'master_url' => (string)$con->opts->optGet( 'importexport_masterurl' ),
				'sync_site_urls' => \array_values( \array_map(
					static fn( array $row ) :string => (string)$row[ 'url' ],
					$this->registryRows()
				) ),
				'import_enabled' => (string)$con->opts->optGet( 'importexport_enable' ),
				'notify_hook' => $this->notifyHook(),
				'import_hook' => $this->importHook(),
				'queue_hook' => $this->queueHook(),
				'registry' => $this->registryRows(),
			];
		}

		/**
		 * @return array<string,mixed>
		 */
		private function applyCorpus() :array {
			$con = RuntimeTestState::controller();
			$exportBefore = ( new Export() )->getRawOptionsExport();
			$nonCorpusKeys = $this->nonCorpusKeys();
			$applied = [];
			$generated = [];

			foreach ( $exportBefore as $key => $currentValue ) {
				$key = (string)$key;
				if ( \in_array( $key, $nonCorpusKeys, true ) ) {
					continue;
				}
				$value = $this->valueForOption( $key, $currentValue );
				$con->opts->optSet( $key, $value );
				$generated[ $key ] = $value;
				$applied[] = $key;
			}
			$con->opts->store();
			if ( !( new ProfileRepository() )->copyCurrentSiteConfigToDefaultProfile() ) {
				throw new \RuntimeException( 'Default import/export profile did not refresh from the generated corpus.' );
			}

			$stored = ( new Export() )->getRawOptionsExport();
			$uncovered = \array_values( \array_diff(
				\array_keys( $stored ),
				\array_merge( $applied, $nonCorpusKeys )
			) );
			if ( !empty( $uncovered ) ) {
				throw new \RuntimeException( 'Transferable options were not covered by the generated corpus: '.\implode( ', ', $uncovered ) );
			}

			$normalised = [];
			$unchanged = [];
			foreach ( $generated as $key => $value ) {
				if ( !\array_key_exists( $key, $stored ) ) {
					throw new \RuntimeException( 'Generated corpus option missing from stored export: '.$key );
				}
				if ( \serialize( $stored[ $key ] ) === \serialize( $exportBefore[ $key ] ?? null ) ) {
					$unchanged[] = $key;
					continue;
				}
				if ( \serialize( $stored[ $key ] ) !== \serialize( $value ) ) {
					$normalised[] = $key;
				}
			}
			if ( !empty( $unchanged ) ) {
				throw new \RuntimeException( 'Generated corpus options did not change from baseline after storage: '.\implode( ', ', $unchanged ) );
			}

			$notifyHook = $this->notifyHook();
			do_action( 'shield/after_form_submit_options_save', [
				'all_opts_keys' => \implode( ',', $applied ),
			] );

			return [
				'applied_keys' => $applied,
				'local_state_exceptions' => self::LOCAL_STATE_EXCEPTIONS,
				'runtime_invariant_keys' => self::RUNTIME_INVARIANT_KEYS,
				'normalised_keys' => $normalised,
				'notify_hook' => $notifyHook,
				'export_count' => \count( $stored ),
			];
		}

		/**
		 * @return array<string,mixed>
		 */
		private function runNotifyHook() :array {
			do_action( 'shield/after_form_submit_options_save' );
			return [
				'event' => 'shield/after_form_submit_options_save',
				'notify_hook' => $this->notifyHook(),
			];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @return array<string,mixed>
		 */
		private function legacyMigrationCheck( array $payload ) :array {
			RuntimeTestState::ensureDb( [ ImportExportProfilesDB::DB_KEY, ImportExportSitesDB::DB_KEY ] );
			$this->clearImportExportRuntimeState();

			$slaveUrl = (string)( $payload[ 'slave_url' ] ?? '' );
			$extraUrl = (string)( $payload[ 'extra_url' ] ?? 'https://legacy-extra.example.com' );
			$unknownOldQueueUrl = 'https://old-queue-only.example.com';
			$repo = new SiteRepository();
			$slaveUrl = $repo->canonicalizeUrl( $slaveUrl );
			$extraUrl = $repo->canonicalizeUrl( $extraUrl );
			if ( empty( $slaveUrl ) || empty( $extraUrl ) ) {
				throw new \RuntimeException( 'Legacy migration check requires valid slave and extra URLs.' );
			}

			$con = RuntimeTestState::controller();
			$con->opts
				->optSet( 'importexport_whitelist', [ $slaveUrl, $slaveUrl, $extraUrl ] )
				->optSet( 'import_url_ids', [
					\hash( 'md5', $slaveUrl ) => 'legacy-slave-id',
					\hash( 'md5', $extraUrl ) => 'legacy-extra-id',
				] )
				->store();
			$this->pushLegacyQueueUrls( [ $slaveUrl, $unknownOldQueueUrl ] );

			$repo->ensureLegacyImported();

			return [
				'slave_url' => $slaveUrl,
				'extra_url' => $extraUrl,
				'unknown_old_queue_url' => $unknownOldQueueUrl,
				'whitelist' => \array_values( $con->opts->optGet( 'importexport_whitelist' ) ),
				'import_url_ids' => $con->opts->optGet( 'import_url_ids' ),
				'rows' => $this->registryRows(),
				'unknown_old_queue_row_exists' => $repo->findByUrl( $unknownOldQueueUrl, true ) !== null,
				'legacy_batch_count' => \count( $this->legacyQueue()->get_batches() ),
			];
		}

		/**
		 * Read-only semantic snapshot of the current import/export migration state.
		 *
		 * @return array<string,mixed>
		 */
		private function migrationState() :array {
			$con = RuntimeTestState::controller();
			$profileableKeys = ( new ProfileOptionsCatalog() )->profileableKeys();
			\sort( $profileableKeys );
			$profileableOptions = \array_intersect_key(
				( new Export() )->getFullTransferableOptionsExport(),
				\array_flip( $profileableKeys )
			);
			\ksort( $profileableOptions );

			$profiles = [];
			try {
				$dbh = $con->db_con->import_export_profiles;
				if ( $dbh instanceof ImportExportProfilesDB && $dbh->isReady() ) {
					foreach ( $dbh->getQuerySelector()->setOrderBy( 'id', 'ASC' )->queryWithResult() ?? [] as $profile ) {
						if ( $profile instanceof ImportExportProfileRecord ) {
							$profiles[] = $this->normaliseProfile( $profile, $profileableKeys );
						}
					}
				}
			}
			catch ( \Throwable $e ) {
			}

			$defaultProfileIDs = \array_values( \array_map(
				static fn( array $profile ) :int => $profile[ 'id' ],
				\array_filter( $profiles, static fn( array $profile ) :bool => $profile[ 'is_default' ] )
			) );

			return [
				'migration_completed' => (int)$con->opts->optGet( 'importexport_sites_migrated_at' ) > 0,
				'root_xfer_excluded' => $this->normaliseStringList( $con->opts->optGet( 'xfer_excluded' ) ),
				'profileable_keys'    => $profileableKeys,
				'profileable_options' => $profileableOptions,
				'default_profile_ids' => $defaultProfileIDs,
				'profiles'            => $profiles,
				'active_registry'     => $this->migrationRegistryRows( $defaultProfileIDs ),
				'master_sync_enabled' => (string)$con->opts->optGet( 'importexport_enable' ),
				'master_sync_urls'    => \array_values( \array_map(
					static fn( ImportExportSiteRecord $row ) :string => $row->url,
					( new SiteRepository() )->selectActiveRows()
				) ),
			];
		}

		/**
		 * @return array<string,mixed>
		 */
		private function queueState() :array {
			$rows = $this->registryRows();
			$now = Services::Request()->ts();
			$queueNext = \wp_next_scheduled( $this->queueHook() );
			return [
				'queue_hook' => $this->queueHook(),
				'queue_scheduled' => $queueNext !== false,
				'queue_next' => $queueNext === false ? 0 : (int)$queueNext,
				'processor_running' => \get_site_transient( $this->processorMarkerKey() ) !== false,
				'active_count' => \count( $rows ),
				'due_count' => \count( \array_filter(
					$rows,
					static fn( array $row ) :bool => \in_array(
						$row[ 'queue_status' ] ?? '',
						[ ImportExportSitesDB::QUEUE_IDLE, ImportExportSitesDB::QUEUE_QUEUED ],
						true
					) && (int)( $row[ 'next_ping_at' ] ?? 0 ) <= $now
				) ),
				'waiting_export_count' => \count( \array_filter(
					$rows,
					static fn( array $row ) :bool => ( $row[ 'queue_status' ] ?? '' ) === ImportExportSitesDB::QUEUE_WAITING_EXPORT
				) ),
				'rows' => $rows,
			];
		}

		private function advanceQueueEvent() :array {
			$hook = $this->queueHook();
			$scheduled = \wp_next_scheduled( $hook );
			if ( $scheduled === false ) {
				throw new \RuntimeException( 'Cannot advance a missing production queue event.' );
			}
			\wp_unschedule_event( (int)$scheduled, $hook );
			$advanced = Services::Request()->ts() - \MINUTE_IN_SECONDS;
			if ( !\wp_schedule_single_event( $advanced, $hook ) ) {
				throw new \RuntimeException( 'Could not advance the production queue event.' );
			}
			\delete_transient( 'doing_cron' );
			return [
				'queue_hook' => $hook,
				'original_timestamp' => (int)$scheduled,
				'advanced_timestamp' => $advanced,
				'cron_lock_cleared' => \get_transient( 'doing_cron' ) === false,
			];
		}

		private function groupCSetup( string $mode ) :array {
			if ( !\in_array( $mode, [ 'healthy', 'terminated' ], true ) ) {
				throw new \RuntimeException( 'Unsupported Group C queue mode.' );
			}
			$repo = new SiteRepository();
			$oldIDs = [];
			$targetBaseUrl = '';
			foreach ( $repo->selectActiveRows() as $row ) {
				if ( \strpos( $row->url, 'group-c-queue-' ) !== false ) {
					$oldIDs[] = $row->id;
				}
				elseif ( $targetBaseUrl === '' ) {
					$targetBaseUrl = \rtrim( $row->url, '/' );
				}
			}
			if ( $targetBaseUrl === '' ) {
				throw new \RuntimeException( 'Group C queue evidence requires the configured slave site.' );
			}
			$repo->deleteByIds( $oldIDs );
			\update_option( 'shield_group_c_queue_state', [
				'mode' => $mode,
				'ajax_entries' => 0,
				'cron_entries' => 0,
				'dispatch_attempts' => 0,
				'notification_starts' => 0,
				'attempt_counters' => [],
				'future_event_observed' => false,
			], false );
			foreach ( [ 'one', 'two' ] as $suffix ) {
				$url = sprintf( '%s/group-c-queue-%s-%s', $targetBaseUrl, $mode, $suffix );
				if ( !$repo->upsertActive( $url, ImportExportSitesDB::SOURCE_MANUAL, 'group-c-'.$suffix, true ) instanceof ImportExportSiteRecord ) {
					throw new \RuntimeException( 'Could not seed Group C queue row.' );
				}
			}
			RuntimeTestState::controller()->comps->import_export->scheduleQueueSoonIfSyncEnabled( 1 );
			return $this->groupCState( $mode );
		}

		private function groupCState( string $mode ) :array {
			$state = \get_option( 'shield_group_c_queue_state', [] );
			$rows = \array_values( \array_filter(
				$this->registryRows(),
				static fn( array $row ) :bool => \strpos( (string)( $row[ 'url' ] ?? '' ), 'group-c-queue-'.$mode.'-' ) !== false
			) );
			return [
				'mode' => $mode,
				'queue_hook' => $this->queueHook(),
				'queue_next' => (int)( \wp_next_scheduled( $this->queueHook() ) ?: 0 ),
				'expected_ajax_action' => $this->processorIdentifier(),
				'processor_running' => \get_site_transient( $this->processorMarkerKey() ) !== false,
				'rows' => $rows,
				'ajax_entries' => \is_array( $state ) ? (int)( $state[ 'ajax_entries' ] ?? 0 ) : 0,
				'cron_entries' => \is_array( $state ) ? (int)( $state[ 'cron_entries' ] ?? 0 ) : 0,
				'dispatch_attempts' => \is_array( $state ) ? (int)( $state[ 'dispatch_attempts' ] ?? 0 ) : 0,
				'dispatch_blocking' => \is_array( $state ) && !empty( $state[ 'dispatch_blocking' ] ),
				'dispatch_url' => \is_array( $state ) ? (string)( $state[ 'dispatch_url' ] ?? '' ) : '',
				'notification_starts' => \is_array( $state ) ? (int)( $state[ 'notification_starts' ] ?? 0 ) : 0,
				'attempt_counters' => \is_array( $state ) ? (array)( $state[ 'attempt_counters' ] ?? [] ) : [],
				'future_event_observed' => \is_array( $state ) && !empty( $state[ 'future_event_observed' ] ),
			];
		}

		private function groupCRecover() :array {
			$state = \get_option( 'shield_group_c_queue_state', [] );
			$state = \is_array( $state ) ? $state : [];
			$state[ 'mode' ] = 'recovery';
			\update_option( 'shield_group_c_queue_state', $state, false );
			\delete_site_transient( $this->processorMarkerKey() );
			$repo = new SiteRepository();
			foreach ( $repo->selectActiveRows() as $row ) {
				if ( $row->queue_status === ImportExportSitesDB::QUEUE_PROCESSING
					 && \strpos( $row->url, 'group-c-queue-' ) !== false ) {
					RuntimeTestState::controller()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
						'lock_until' => Services::Request()->ts() - 1,
					] );
				}
			}
			return $this->advanceQueueEvent();
		}

		/**
		 * @return array<string,mixed>
		 */
		private function cronState() :array {
			$con = RuntimeTestState::controller();
			$masterUrl = (string)$con->opts->optGet( 'importexport_masterurl' );

			return [
				'import_hook' => $this->importHook(),
				'import_scheduled' => \wp_next_scheduled( $this->importHook() ) !== false,
				'notify_hook' => $this->notifyHook(),
				'notify_scheduled' => \wp_next_scheduled( $this->notifyHook() ) !== false,
				'notify_cooldown_active' => $this->notifyCooldownActive( $masterUrl ),
				'queue_hook' => $this->queueHook(),
				'queue_scheduled' => \wp_next_scheduled( $this->queueHook() ) !== false,
				'master_url' => $masterUrl,
				'import_id' => (string)$con->opts->optGet( 'import_id' ),
			];
		}

		/**
		 * @return array<string,mixed>
		 */
		private function b2PrepareMasterRow() :array {
			$repo = new SiteRepository();
			$rows = $repo->selectActiveRows();
			if ( \count( $rows ) !== 1 || !$rows[ 0 ] instanceof ImportExportSiteRecord ) {
				throw new \RuntimeException( 'B2 master-row preparation requires one active client row.' );
			}

			$row = $rows[ 0 ];
			$meta = \is_array( $row->meta ) ? $row->meta : [];
			unset( $meta[ 'export_served_at' ], $meta[ 'handshake_attempt_at' ], $meta[ 'sync_observations' ] );

			$dbh = RuntimeTestState::controller()->db_con->import_export_sites;
			$dbh->getQueryUpdater()->updateById( $row->id, [
				'import_id'               => '',
				'last_export_request_at'  => 0,
				'last_export_success_at'  => 0,
				'last_export_failure_at'  => 0,
				'last_export_result_code' => '',
				'last_export_error'       => '',
				'meta'                    => $dbh->getRecord()->arrayDataWrap( $meta ) ?? '',
			] );
			$snapshot = $this->b2Snapshot( 'master', $row->url );
			if ( !empty( $snapshot[ 'row' ][ 'stored_import_id_present' ] )
				 || (int)( $snapshot[ 'row' ][ 'handshake_attempt_at' ] ?? 0 ) !== 0 ) {
				throw new \RuntimeException( 'Could not prepare the B2 master row.' );
			}

			return $snapshot;
		}

		/**
		 * @return array<string,mixed>
		 */
		private function b2PrepareClient() :array {
			( new ObservationStore() )->deleteClientImport();
			$con = RuntimeTestState::controller();
			$con->opts
				->optSet( 'importexport_handshake_expires_at', 0 )
				->store();

			return $this->b2Snapshot( 'client', '' );
		}

		/**
		 * @return array<string,mixed>
		 */
		private function b2PrepareRetryAfterCooldown() :array {
			$prepared = $this->b2PrepareMasterRow();
			$repo = new SiteRepository();
			$row = $repo->findByUrl( (string)( $prepared[ 'row' ][ 'url' ] ?? '' ), true );
			if ( !$row instanceof ImportExportSiteRecord ) {
				throw new \RuntimeException( 'B2 retry preparation could not reload the client row.' );
			}

			$meta = \is_array( $row->meta ) ? $row->meta : [];
			$meta[ 'handshake_attempt_at' ] = Services::Request()->ts() - 300;
			$dbh = RuntimeTestState::controller()->db_con->import_export_sites;
			$dbh->getQueryUpdater()->updateById( $row->id, [
				'meta' => $dbh->getRecord()->arrayDataWrap( $meta ) ?? '',
			] );

			$snapshot = $this->b2Snapshot( 'master', $row->url );
			if ( !empty( $snapshot[ 'row' ][ 'stored_import_id_present' ] )
				 || !empty( $snapshot[ 'row' ][ 'callback_cooldown_active' ] )
				 || (int)( $snapshot[ 'row' ][ 'callback_eligible_at' ] ?? 0 ) > (int)( $snapshot[ 'observed_at' ] ?? 0 ) ) {
				throw new \RuntimeException( 'Could not prepare the B2 retry after callback cooldown.' );
			}
			return $snapshot;
		}

		/**
		 * @return array{active:bool,count:int,positive_control_count:int}
		 */
		private function b2ResetCallbackObserver() :array {
			$observer = $this->b2CallbackObserver();
			if ( !$observer[ 'active' ] ) {
				throw new \RuntimeException( 'B2 callback observer fixture is not active.' );
			}
			update_option( SHIELD_CROSS_SITE_B2_CALLBACK_COUNT_OPTION, 0, false );

			$probe = wp_remote_get(
				RuntimeTestState::controller()->plugin_urls->noncedPluginAction(
					PluginImportExport_HandshakeConfirm::class,
					home_url(),
					[ 'uniq' => wp_generate_password( 8, false ) ]
				),
				[
					'timeout' => 5,
					'redirection' => 0,
					'reject_unsafe_urls' => false,
				]
			);
			if ( is_wp_error( $probe ) ) {
				throw new \RuntimeException( 'B2 callback observer positive control failed: '.$probe->get_error_message() );
			}

			wp_cache_delete( SHIELD_CROSS_SITE_B2_CALLBACK_COUNT_OPTION, 'options' );
			$positiveControl = $this->b2CallbackObserver();
			if ( $positiveControl[ 'count' ] !== 1 ) {
				throw new \RuntimeException( 'B2 callback observer did not record its HTTP positive control.' );
			}

			update_option( SHIELD_CROSS_SITE_B2_CALLBACK_COUNT_OPTION, 0, false );
			$reset = $this->b2CallbackObserver();
			if ( $reset[ 'count' ] !== 0 ) {
				throw new \RuntimeException( 'B2 callback observer did not reset after its positive control.' );
			}
			$reset[ 'positive_control_count' ] = $positiveControl[ 'count' ];
			return $reset;
		}

		/**
		 * @return array<string,mixed>
		 */
		private function b2Snapshot( string $role, string $expectedUrl ) :array {
			if ( !\in_array( $role, [ 'master', 'client' ], true ) ) {
				throw new \RuntimeException( 'Unsupported B2 snapshot role.' );
			}

			$con = RuntimeTestState::controller();
			$now = Services::Request()->ts();
			$handshakeExpiresAt = (int)$con->opts->optGet( 'importexport_handshake_expires_at' );
			$snapshot = [
				'role'                  => $role,
				'home_url'              => ( new SyncSiteUrlValidator() )->canonicalize( Services::WpGeneral()->getHomeUrl() ),
				'master_url'            => ( new SyncSiteUrlValidator() )->canonicalize( (string)$con->opts->optGet( 'importexport_masterurl' ) ),
				'observed_at'           => $now,
				'local_import_id_present' => \trim( (string)$con->opts->optGet( 'import_id' ) ) !== '',
				'handshake_expires_at'  => $handshakeExpiresAt,
				'handshake_eligible'    => $handshakeExpiresAt > $now,
				'client_import'         => ( new ObservationStore() )->readClientImport(),
			];

			if ( $role === 'client' ) {
				$snapshot[ 'callback_observer' ] = $this->b2CallbackObserver();
				return $snapshot;
			}

			$repo = new SiteRepository();
			$canonicalExpectedUrl = $repo->canonicalizeUrl( $expectedUrl );
			$row = $canonicalExpectedUrl === '' ? null : $repo->findByUrl( $canonicalExpectedUrl, true );
			$trustedTarget = false;
			if ( $row instanceof ImportExportSiteRecord ) {
				try {
					( new SyncSiteUrlValidator() )->validateTrustedSyncUrl( $row->url );
					$trustedTarget = true;
				}
				catch ( \Throwable $e ) {
				}
			}

			$meta = $row instanceof ImportExportSiteRecord && \is_array( $row->meta ) ? $row->meta : [];
			$snapshot[ 'expected_url' ] = $canonicalExpectedUrl;
			$snapshot[ 'row' ] = $row instanceof ImportExportSiteRecord ? [
				'associated'              => true,
				'url'                     => $row->url,
				'status'                  => $row->status,
				'not_deleted'             => $row->deleted_at === 0,
				'source'                  => $row->source,
				'trusted_target'          => $trustedTarget,
				'stored_import_id_present' => \trim( $row->import_id ) !== '',
				'handshake_attempt_at'    => (int)( $meta[ 'handshake_attempt_at' ] ?? 0 ),
				'callback_eligible_at'    => (int)( $meta[ 'handshake_attempt_at' ] ?? 0 ) > 0
					? (int)$meta[ 'handshake_attempt_at' ] + 300
					: 0,
				'callback_cooldown_active' => $repo->handshakeCooldownActive( $row, 300 ),
				'export_served_at'        => (int)( $meta[ 'export_served_at' ] ?? 0 ),
				'last_export_request_at'  => $row->last_export_request_at,
				'last_export_success_at'  => $row->last_export_success_at,
				'last_export_failure_at'  => $row->last_export_failure_at,
				'last_export_result_code' => $row->last_export_result_code,
				'verification'            => $repo->readObservation( $row, SyncObservation::PHASE_VERIFICATION ),
				'export'                  => $repo->readObservation( $row, SyncObservation::PHASE_EXPORT ),
			] : [
				'associated' => false,
			];
			$snapshot[ 'unassociated_rejection' ] = ( new ObservationStore() )->readUnassociatedRejection();
			return $snapshot;
		}

		/**
		 * @return array{active:bool,count:int}
		 */
		private function b2CallbackObserver() :array {
			$active = \defined( 'SHIELD_CROSS_SITE_B2_CALLBACK_OBSERVER_ACTIVE' )
				&& SHIELD_CROSS_SITE_B2_CALLBACK_OBSERVER_ACTIVE === true
				&& \defined( 'SHIELD_CROSS_SITE_B2_CALLBACK_COUNT_OPTION' );
			return [
				'active' => $active,
				'count' => $active ? \max( 0, (int)get_option( SHIELD_CROSS_SITE_B2_CALLBACK_COUNT_OPTION, 0 ) ) : 0,
			];
		}

		/**
		 * @return array<string,mixed>
		 */
		private function b2Pull() :array {
			$startedAt = \hrtime( true );
			$import = new Import();
			$success = false;
			$errorClass = null;
			$errorCode = null;
			try {
				$import->fromSite( '', '', null, Import::REQUEST_SAFETY_TRUSTED_SYNC );
				$success = true;
			}
			catch ( \Throwable $e ) {
				$errorClass = \get_class( $e );
				$errorCode = (int)$e->getCode();
			}

			return [
				'success'       => $success,
				'duration_ms'   => (int)\round( ( \hrtime( true ) - $startedAt ) / 1000000 ),
				'error_class'   => $errorClass,
				'error_code'    => $errorCode,
				'client_import' => $import->latestObservation(),
			];
		}

		/**
		 * Send the existing export action without Import::fromSite(), which would renew
		 * callback eligibility before the request.
		 *
		 * @return array<string,mixed>
		 */
		private function b2ExpiredExportRequest() :array {
			$con = RuntimeTestState::controller();
			$expiresAt = Services::Request()->ts() - 1;
			$con->opts
				->optSet( 'importexport_handshake_expires_at', $expiresAt )
				->store();

			$masterUrl = ( new SyncSiteUrlValidator() )->validateTrustedSyncUrl(
				(string)$con->opts->optGet( 'importexport_masterurl' )
			);
			$importID = \trim( (string)$con->opts->optGet( 'import_id' ) );
			$targetUrl = $con->plugin_urls->noncedPluginAction(
				PluginImportExport_Export::class,
				$masterUrl,
				[
					'url' => Services::WpGeneral()->getHomeUrl(),
					'id' => $importID,
					'method' => 'json',
					'uniq' => wp_generate_password( 4, false ),
				]
			);

			$sentAt = Services::Request()->ts();
			$storedExpiresAt = (int)$con->opts->optGet( 'importexport_handshake_expires_at' );
			if ( $storedExpiresAt > $sentAt ) {
				throw new \RuntimeException( 'B2 expired callback request was eligible at the send boundary.' );
			}
			$sendBoundary = [
				'handshake_expires_at' => $storedExpiresAt,
				'sent_at' => $sentAt,
				'handshake_eligible' => false,
			];

			$startedAt = \hrtime( true );
			$http = Services::HttpRequest();
			try {
				$body = ( new ScopedTargetHostRequest() )->run(
					$targetUrl,
					static fn() :string => $http->getContent( $targetUrl, [
						'reject_unsafe_urls' => true,
					] )
				);
				$outcome = HttpOutcome::fromRequest( $body, $http );
				$decoded = @\json_decode( $body, true );
				$responseClass = $body === '' ? 'empty_response' : ( \is_array( $decoded ) ? 'json_object' : 'non_json_response' );

				return [
					'send_boundary' => $sendBoundary,
					'submitted_import_id_present' => $importID !== '',
					'has_response' => $outcome->hasResponse(),
					'http_status' => $outcome->status(),
					'response_class' => $responseClass,
					'duration_ms' => (int)\round( ( \hrtime( true ) - $startedAt ) / 1000000 ),
				];
			}
			catch ( \Throwable $e ) {
				return [
					'send_boundary' => $sendBoundary,
					'submitted_import_id_present' => $importID !== '',
					'has_response' => false,
					'http_status' => null,
					'response_class' => 'transport_exception',
					'error_class' => \get_class( $e ),
					'duration_ms' => (int)\round( ( \hrtime( true ) - $startedAt ) / 1000000 ),
				];
			}
		}

		/**
		 * @return array<string,mixed>
		 */
		private function exportOptions() :array {
			return [
				'options' => ( new Export() )->getRawOptionsExport(),
				'local_state_exceptions' => self::LOCAL_STATE_EXCEPTIONS,
				'runtime_invariant_keys' => self::RUNTIME_INVARIANT_KEYS,
			];
		}

		/**
		 * @return list<array<string,int|string>>
		 */
		private function registryRows() :array {
			$repo = new SiteRepository();
			return \array_map(
				fn( ImportExportSiteRecord $row ) :array => $this->normaliseRegistryRow( $row ),
				$repo->selectActiveRows()
			);
		}

		/**
		 * @return array<string,mixed>
		 */
		private function normaliseRegistryRow( ImportExportSiteRecord $row ) :array {
			$rowData = $row->getRawData();
			$rowData[ 'meta' ] = $row->meta;
			return $rowData;
		}

		/**
		 * @param string[] $profileableKeys
		 * @return array<string,mixed>
		 */
		private function normaliseProfile( ImportExportProfileRecord $profile, array $profileableKeys ) :array {
			$config = \json_decode( $profile->config, true );
			$config = \is_array( $config ) ? $config : [];
			$options = \is_array( $config[ 'options' ] ?? null ) ? $config[ 'options' ] : [];
			\ksort( $options );

			return [
				'id'                          => $profile->id,
				'slug'                        => $profile->slug,
				'label'                       => $profile->label,
				'is_default'                  => $profile->is_default,
				'options'                     => $options,
				'excluded'                    => $this->normaliseStringList( $config[ 'excluded' ] ?? [] ),
				'non_profileable_option_keys' => \array_values( \array_diff( \array_keys( $options ), $profileableKeys ) ),
			];
		}

		/**
		 * @param int[] $defaultProfileIDs
		 * @return list<array<string,int|string|bool>>
		 */
		private function migrationRegistryRows( array $defaultProfileIDs ) :array {
			$rows = [];
			foreach ( ( new SiteRepository() )->selectActiveRows() as $row ) {
				$rows[] = [
					'url'                         => $row->url,
					'import_id'                   => $row->import_id,
					'source'                      => $row->source,
					'profile_ref'                 => $row->profile_ref,
					'profile_resolves_to_default' => \in_array( $row->profile_ref, $defaultProfileIDs, true ),
				];
			}
			return $rows;
		}

		/**
		 * @param mixed $values
		 * @return string[]
		 */
		private function normaliseStringList( $values ) :array {
			$values = \array_values( \array_unique( \array_map( '\\strval', \is_array( $values ) ? $values : [] ) ) );
			\sort( $values );
			return $values;
		}

		/**
		 * @param list<string> $urls
		 */
		private function pushLegacyQueueUrls( array $urls ) :void {
			$queue = $this->legacyQueue();
			foreach ( $urls as $url ) {
				$queue->push_to_queue( $url );
			}
			$queue->save();
		}

		/**
		 * @return string[]
		 */
		private function requiredCapabilities() :array {
			$capabilities = [ 'wpcli_level_2' ];
			foreach ( RuntimeTestState::controller()->cfg->configuration->transferableOptions() as $option ) {
				$cap = (string)( $option[ 'cap' ] ?? '' );
				if ( $cap !== '' ) {
					$capabilities[] = $cap;
				}
			}
			return \array_values( \array_unique( \array_filter( $capabilities ) ) );
		}

		/**
		 * @param mixed $currentValue
		 * @return mixed
		 */
		private function valueForOption( string $key, $currentValue ) {
			$con = RuntimeTestState::controller();
			$def = $con->opts->optDef( $key );
			switch ( $con->opts->optType( $key ) ) {
				case 'checkbox':
					return (string)$currentValue === 'Y' ? 'N' : 'Y';

				case 'integer':
					return $this->integerValue( $def, $currentValue );

				case 'email':
					return 'cross-site@example.com';

				case 'password':
					return 'cross-site-password-'.$key;

				case 'text':
					return $this->textValue( $key );

				case 'select':
					return $this->selectValue( $def, $currentValue );

				case 'multiple_select':
					return $this->multipleSelectValue( $key, $def, $currentValue );

				case 'array':
					return $this->arrayValue( $key );

				case 'boolean':
					return !\is_bool( $currentValue ) || !$currentValue;

				default:
					return $currentValue;
			}
		}

		/**
		 * @param array<string,mixed> $def
		 */
		private function integerValue( array $def, $currentValue ) :int {
			$current = (int)$currentValue;
			$default = (int)( $def[ 'default' ] ?? 0 );
			$min = isset( $def[ 'min' ] ) ? (int)$def[ 'min' ] : null;
			$max = isset( $def[ 'max' ] ) ? (int)$def[ 'max' ] : null;
			$candidates = [ $current + 1, $current - 1, $default + 1, $default - 1, 7, 1, 0 ];
			foreach ( $candidates as $candidate ) {
				if ( $candidate === $current ) {
					continue;
				}
				if ( $min !== null && $candidate < $min ) {
					continue;
				}
				if ( $max !== null && $candidate > $max ) {
					continue;
				}
				return $candidate;
			}
			return $current;
		}

		private function textValue( string $key ) :string {
			switch ( $key ) {
				case 'wl_homeurl':
					return 'https://example.com/shield-cross-site';
				case 'wl_menuiconurl':
				case 'wl_dashboardlogourl':
				case 'wl_login2fa_logourl':
					return 'https://example.com/'.$key.'.png';
				case 'rename_wplogin_path':
					return 'shield-login';
				case 'rename_wplogin_redirect':
					return 'shield-redirect';
				case 'preferred_temp_dir':
					return \sys_get_temp_dir();
				case 'language_override':
					return 'fr';
				default:
					return 'cross-site-'.$key;
			}
		}

		/**
		 * @param array<string,mixed> $def
		 */
		private function selectValue( array $def, $currentValue ) :string {
			$current = (string)$currentValue;
			foreach ( (array)( $def[ 'value_options' ] ?? [] ) as $option ) {
				$value = (string)( $option[ 'value_key' ] ?? '' );
				if ( $value !== '' && $value !== $current ) {
					return $value;
				}
			}
			return $current;
		}

		/**
		 * @param array<string,mixed> $def
		 * @return string[]
		 */
		private function multipleSelectValue( string $key, array $def, $currentValue ) :array {
			$current = \array_map( 'strval', (array)$currentValue );
			switch ( $key ) {
				case 'admin_access_restrict_plugins':
					return $current === [ 'install_plugins', 'update_plugins' ]
						? []
						: [ 'install_plugins', 'update_plugins' ];
				case 'admin_access_restrict_posts':
					return $current === [ 'publish', 'delete' ] ? [] : [ 'publish', 'delete' ];
			}

			$values = [];
			foreach ( (array)( $def[ 'value_options' ] ?? [] ) as $option ) {
				$value = (string)( $option[ 'value_key' ] ?? '' );
				if ( $value !== '' && !\in_array( $value, $current, true ) ) {
					$values[] = $value;
				}
				if ( \count( $values ) >= 2 ) {
					break;
				}
			}
			return $values !== [] ? $values : $current;
		}

		/**
		 * @return string[]
		 */
		private function arrayValue( string $key ) :array {
			switch ( $key ) {
				case 'sec_admin_users':
					return [ 'admin' ];
				case 'trusted_user_roles':
				case 'two_factor_auth_user_roles':
				case 'auto_idle_roles':
					return [ 'administrator', 'editor' ];
				case 'page_params_whitelist':
					return [ 'sample-page,param_one,param_two' ];
				case 'scan_path_exclusions':
					return [ 'wp-content/cache/cross-site/' ];
				case 'request_whitelist':
					return [ '/cross-site-test/*' ];
				case 'api_namespace_exclusions':
					return [ 'shield', 'cross-site' ];
				case 'xcsp_custom':
					return [ "default-src 'self'" ];
				default:
					return [ 'cross-site-'.$key ];
			}
		}

		/**
		 * @return string[]
		 */
		private function nonCorpusKeys() :array {
			return \array_values( \array_unique( \array_merge(
				self::LOCAL_STATE_EXCEPTIONS,
				self::RUNTIME_INVARIANT_KEYS
			) ) );
		}

		private function primeCorpusBaselines() :void {
			RuntimeTestState::forcePersistOptions( [
				'enable_mu' => 'Y',
				'enable_wpvuln_scan' => 'N',
			] );
			RuntimeTestState::resetOptionsRuntimeCache();
		}

		private function clearImportExportRuntimeState() :void {
			$con = RuntimeTestState::controller();
			foreach ( [
				$this->notifyHook(),
				$this->importHook(),
				$this->queueHook(),
				$this->legacyQueueHook(),
			] as $hook ) {
				\wp_clear_scheduled_hook( $hook );
			}
			$this->legacyQueue()->delete_all();

			global $wpdb;
			try {
				$table = RuntimeTestState::requireDbHandler( ImportExportSitesDB::DB_KEY, true )->getTable();
				$wpdb->query( "DELETE FROM `{$table}`" );
				Services::WpDb()->clearResultShowTables();
			}
			catch ( \Throwable $e ) {
			}
			try {
				$table = RuntimeTestState::requireDbHandler( ImportExportProfilesDB::DB_KEY, true )->getTable();
				$wpdb->query( "DELETE FROM `{$table}`" );
				Services::WpDb()->clearResultShowTables();
			}
			catch ( \Throwable $e ) {
			}
			if ( isset( $wpdb ) && isset( $wpdb->options ) ) {
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'%whitelist_notify_urls%'
				) );
				foreach ( [ '_transient_', '_transient_timeout_' ] as $transientPrefix ) {
					$wpdb->query( $wpdb->prepare(
						"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
						$wpdb->esc_like( $transientPrefix.$con->prefix( 'importexport_updatenotified_' ) ).'%'
					) );
				}
			}
			$con->opts
				->optSet( 'importexport_whitelist', [] )
				->optSet( 'import_url_ids', [] )
				->optSet( 'importexport_sites_migrated_at', 0 )
				->optSet( 'importexport_handshake_expires_at', 0 )
				->store();
		}

		private function notifyHook() :string {
			return RuntimeTestState::controller()->prefix( 'importexport_notify' );
		}

		private function importHook() :string {
			return RuntimeTestState::controller()->prefix( PluginImportExport_UpdateNotified::SLUG );
		}

		private function queueHook() :string {
			return RuntimeTestState::controller()->prefix( QueueScheduler::HOOK );
		}

		private function processorIdentifier() :string {
			return RuntimeTestState::controller()->getPluginPrefix( '_' )
				   .'_importexport_sites_queue_'.\get_current_blog_id();
		}

		private function processorMarkerKey() :string {
			return $this->processorIdentifier().'_process_lock';
		}

		private function legacyQueueHook() :string {
			$queue = $this->legacyQueue();
			$reflection = new \ReflectionClass( \FernleafSystems\Wordpress\Services\TP\BackgroundProcessing\WP_Background_Process::class );
			$property = $reflection->getProperty( 'cron_hook_identifier' );
			$property->setAccessible( true );
			return (string)$property->getValue( $queue );
		}

		private function notifyCooldownActive( string $masterUrl ) :bool {
			return \trim( $masterUrl ) !== ''
				   && \get_transient( $this->notifyCooldownKey( $masterUrl ) ) !== false;
		}

		private function notifyCooldownKey( string $masterUrl ) :string {
			return RuntimeTestState::controller()->prefix( 'importexport_updatenotified_' )
				   .\hash( 'sha256', \strtolower( \trim( $masterUrl ) ) );
		}

		private function legacyQueue() :WhitelistNotifyQueue {
			return new WhitelistNotifyQueue( SiteRepository::OLD_QUEUE_ACTION, RuntimeTestState::controller()->prefix() );
		}
	};

	$crossSiteData = $crossSiteRuntime->run( $crossSiteAction, $crossSitePayload );
	echo \wp_json_encode( [
		'ok' => true,
		'action' => $crossSiteAction,
		'data' => $crossSiteData,
	], \JSON_UNESCAPED_SLASHES ).\PHP_EOL;
}
catch ( \Throwable $throwable ) {
	echo \wp_json_encode( [
		'ok' => false,
		'action' => $crossSiteAction,
		'error' => [
			'message' => $throwable->getMessage(),
		],
	], \JSON_UNESCAPED_SLASHES ).\PHP_EOL;
	exit( 1 );
}
