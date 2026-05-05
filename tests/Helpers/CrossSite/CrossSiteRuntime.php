<?php
// WP-CLI eval-file wraps helpers before execution, so this file cannot declare strict_types first.

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_UpdateNotified;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Handler as ImportExportSitesDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Record as ImportExportSiteRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\QueueScheduler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
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
				case 'legacy-migration-check':
					return $this->legacyMigrationCheck( $payload );
				case 'cron-state':
					return $this->cronState();
				case 'export-options':
					return $this->exportOptions();
				default:
					throw new \RuntimeException( 'Unknown cross-site runtime action: '.$action );
			}
		}

		/**
		 * @return array<string,mixed>
		 */
		private function setup( string $role ) :array {
			RuntimeTestState::applyPremiumCapabilities( $this->requiredCapabilities() );
			RuntimeTestState::ensureDb( [ 'file_locker', ImportExportSitesDB::DB_KEY ] );
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
				'whitelist' => \array_values( $con->comps->import_export->getImportExportWhitelist() ),
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
			do_action( $this->notifyHook() );
			return [
				'notify_hook' => $this->notifyHook(),
				'queue' => $this->queueState(),
			];
		}

		/**
		 * @param array<string,mixed> $payload
		 * @return array<string,mixed>
		 */
		private function legacyMigrationCheck( array $payload ) :array {
			RuntimeTestState::ensureDb( [ ImportExportSitesDB::DB_KEY ] );
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
		 * @return array<string,mixed>
		 */
		private function queueState() :array {
			$rows = $this->registryRows();
			$now = Services::Request()->ts();
			return [
				'queue_hook' => $this->queueHook(),
				'queue_scheduled' => \wp_next_scheduled( $this->queueHook() ) !== false,
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

		/**
		 * @return array<string,mixed>
		 */
		private function cronState() :array {
			return [
				'import_hook' => $this->importHook(),
				'import_scheduled' => \wp_next_scheduled( $this->importHook() ) !== false,
				'notify_hook' => $this->notifyHook(),
				'notify_scheduled' => \wp_next_scheduled( $this->notifyHook() ) !== false,
				'queue_hook' => $this->queueHook(),
				'queue_scheduled' => \wp_next_scheduled( $this->queueHook() ) !== false,
			];
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
		 * @return array<string,int|string>
		 */
		private function normaliseRegistryRow( ImportExportSiteRecord $row ) :array {
			return [
				'id' => $row->id,
				'url' => $row->url,
				'import_id' => $row->import_id,
				'status' => $row->status,
				'queue_status' => $row->queue_status,
				'queued_at' => $row->queued_at,
				'next_ping_at' => $row->next_ping_at,
				'expected_export_by' => $row->expected_export_by,
				'last_ping_attempt_at' => $row->last_ping_attempt_at,
				'last_ping_success_at' => $row->last_ping_success_at,
				'last_ping_failure_at' => $row->last_ping_failure_at,
				'last_ping_http_code' => $row->last_ping_http_code,
				'last_export_request_at' => $row->last_export_request_at,
				'last_export_success_at' => $row->last_export_success_at,
				'last_export_failure_at' => $row->last_export_failure_at,
				'last_export_result_code' => $row->last_export_result_code,
				'consecutive_failures' => $row->consecutive_failures,
			];
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
			if ( isset( $wpdb ) && isset( $wpdb->options ) ) {
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					'%whitelist_notify_urls%'
				) );
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

		private function legacyQueueHook() :string {
			$queue = $this->legacyQueue();
			$reflection = new \ReflectionClass( \FernleafSystems\Wordpress\Services\TP\BackgroundProcessing\WP_Background_Process::class );
			$property = $reflection->getProperty( 'cron_hook_identifier' );
			$property->setAccessible( true );
			return (string)$property->getValue( $queue );
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
