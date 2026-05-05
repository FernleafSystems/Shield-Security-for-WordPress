<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\ImportExport;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ImportExportSitesTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates\HandleUpgrade;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\{
	PingSender,
	QueueRunner,
	SiteRepository
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\WhitelistNotifyQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\Request;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportSitesRegistryIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];
	private array $servicesSnapshot = [];
	private ?string $extraColumnTable = null;

	public function set_up() {
		parent::set_up();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'importexport_whitelist',
			'import_url_ids',
			'importexport_sites_migrated_at',
		] );
		$this->requireDb( SitesDB::DB_KEY );
		$this->clearOldQueueState();
	}

	public function tear_down() {
		if ( $this->extraColumnTable !== null ) {
			global $wpdb;
			$wpdb->query( "ALTER TABLE `{$this->extraColumnTable}` DROP COLUMN `extra_probe`" );
			$this->extraColumnTable = null;
			Services::WpDb()->clearResultShowTables();
		}
		$this->clearImportExportSitesReadyCache();
		$this->clearOldQueueState();
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		ServicesState::restore( $this->servicesSnapshot );
		parent::tear_down();
	}

	public function test_legacy_settings_import_into_registry_and_preserve_import_ids() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_whitelist', [
				'https://slave-one.example.com',
				'https://slave-one.example.com',
				'https://slave-two.example.com',
			] )
			->optSet( 'import_url_ids', [
				\hash( 'md5', 'https://slave-one.example.com' ) => 'import-one',
			] )
			->store();

		$this->repo()->ensureLegacyImported( false );

		$one = $this->requireSite( 'https://slave-one.example.com' );
		$two = $this->requireSite( 'https://slave-two.example.com' );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $one->status );
		$this->assertSame( 'import-one', $one->import_id );
		$this->assertSame( '', $two->import_id );
		$this->assertSame(
			[ 'https://slave-one.example.com', 'https://slave-two.example.com' ],
			$con->opts->optGet( 'importexport_whitelist' )
		);
		$this->assertSame( 'import-one', $con->opts->optGet( 'import_url_ids' )[ \hash( 'md5', 'https://slave-one.example.com' ) ] ?? '' );
	}

	public function test_old_queue_only_marks_matching_active_fallback_urls_due() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_whitelist', [
				'https://active.example.com',
				'https://removed.example.com',
			] )
			->store();
		$this->repo()->ensureLegacyImported( false );
		$this->repo()->softDeleteUrl( 'https://removed.example.com' );
		$con->opts->optSet( 'importexport_whitelist', [ 'https://active.example.com' ] )->store();
		$this->pushOldQueueUrls( [
			'https://active.example.com',
			'https://removed.example.com',
			'https://unknown.example.com',
		] );

		$this->repo()->ensureLegacyImported();

		$active = $this->requireSite( 'https://active.example.com' );
		$removed = $this->requireSite( 'https://removed.example.com', true );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $active->queue_status );
		$this->assertSame( SitesDB::STATUS_DELETED, $removed->status );
		$this->assertNull( $this->repo()->findByUrl( 'https://unknown.example.com', true ) );
		$this->assertSame( [], ( new WhitelistNotifyQueue( SiteRepository::OLD_QUEUE_ACTION, $con->prefix() ) )->get_batches() );
	}

	public function test_registry_repairs_from_fallback_after_table_loss() :void {
		$con = $this->requireController();
		$con->opts
			->optSet( 'importexport_whitelist', [ 'https://survives.example.com' ] )
			->optSet( 'import_url_ids', [
				\hash( 'md5', 'https://survives.example.com' ) => 'survive-id',
			] )
			->store();

		$this->dropImportExportSitesTable();
		$this->repo()->ensureLegacyImported( false );

		$row = $this->requireSite( 'https://survives.example.com' );
		$this->assertSame( 'survive-id', $row->import_id );
	}

	public function test_registry_repairs_from_fallback_after_warm_ready_cache_table_loss() :void {
		$con = $this->requireController();
		$url = 'https://cached-loss.example.com';
		$con->opts
			->optSet( 'importexport_whitelist', [ $url ] )
			->optSet( 'import_url_ids', [
				\hash( 'md5', $url ) => 'cached-loss-id',
			] )
			->store();

		$schema = $con->db_con->import_export_sites->getTableSchema();
		SitesDB::GetTableReadyCache()->setReady( $schema );
		$this->dropImportExportSitesTable( false );

		$cachedHandler = $this->newImportExportSitesHandler( true );
		$cachedHandler->execute();
		$this->assertTrue( $cachedHandler->isReady() );
		$this->assertTrue( Services::WpDb()->tableExists( $cachedHandler->getTable() ) );

		$con->db_con->reset();
		$this->repo()->ensureLegacyImported( false );

		$row = $this->requireSite( $url );
		$this->assertSame( 'cached-loss-id', $row->import_id );
		$this->assertSame( [ $url ], $con->opts->optGet( 'importexport_whitelist' ) );
		$this->assertSame( 'cached-loss-id', $con->opts->optGet( 'import_url_ids' )[ \hash( 'md5', $url ) ] ?? '' );
	}

	public function test_scheduled_upgrade_imports_legacy_settings_into_registry() :void {
		$con = $this->requireController();
		$previousVersion = $con->cfg->previous_version;
		$url = 'https://upgrade-import.example.com';
		$con->opts
			->optSet( 'importexport_whitelist', [ $url ] )
			->optSet( 'import_url_ids', [
				\hash( 'md5', $url ) => 'upgrade-import-id',
			] )
			->store();
		$this->dropImportExportSitesTable();

		try {
			$con->cfg->previous_version = '0.0.1';
			( new HandleUpgrade() )->execute();
			do_action( $con->prefix( 'plugin-upgrade' ), '0.0.1' );
		}
		finally {
			$con->cfg->previous_version = $previousVersion;
		}

		$row = $this->requireSite( $url );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $row->status );
		$this->assertSame( 'upgrade-import-id', $row->import_id );
		$this->assertSame( [ $url ], $con->opts->optGet( 'importexport_whitelist' ) );
		$this->assertSame( 'upgrade-import-id', $con->opts->optGet( 'import_url_ids' )[ \hash( 'md5', $url ) ] ?? '' );
	}

	public function test_queue_runner_processes_bounded_batch_and_keeps_sync_success_separate_from_ping() :void {
		$repo = $this->repo();
		for ( $i = 1; $i <= 12; $i++ ) {
			$repo->upsertActive( sprintf( 'https://slave-%02d.example.com', $i ), SitesDB::SOURCE_MANUAL, '', true );
		}

		( new ImportExportQueueRunnerTestDouble( new ImportExportPingSenderTestDouble( true, 204, '' ) ) )->run();

		$waiting = 0;
		$stillDue = 0;
		foreach ( $repo->selectActiveRows() as $row ) {
			if ( $row->queue_status === SitesDB::QUEUE_WAITING_EXPORT ) {
				$waiting++;
				$this->assertGreaterThan( 0, $row->last_ping_success_at );
				$this->assertSame( 0, $row->last_export_success_at );
			}
			if ( $row->queue_status === SitesDB::QUEUE_QUEUED && $row->next_ping_at <= Services::Request()->ts() ) {
				$stillDue++;
			}
		}

		$this->assertSame( 10, $waiting );
		$this->assertSame( 2, $stillDue );
	}

	public function test_failed_ping_records_ping_failure_without_export_success() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://fail-ping.example.com', SitesDB::SOURCE_MANUAL, '', true );

		( new ImportExportQueueRunnerTestDouble( new ImportExportPingSenderTestDouble( false, 503, 'service unavailable' ) ) )->run();

		$row = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $row->queue_status );
		$this->assertGreaterThan( 0, $row->last_ping_failure_at );
		$this->assertSame( 503, $row->last_ping_http_code );
		$this->assertSame( 'service unavailable', $row->last_ping_error );
		$this->assertSame( 0, $row->last_export_success_at );
	}

	public function test_missing_export_request_after_ping_records_export_timeout() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://timeout.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordPingSuccess( $row, 200, Services::Request()->ts() - 1 );

		( new ImportExportQueueRunnerTestDouble( new ImportExportPingSenderTestDouble( true, 200, '' ) ) )->run();

		$row = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $row->queue_status );
		$this->assertGreaterThan( 0, $row->last_export_failure_at );
		$this->assertSame( SitesDB::EXPORT_RESULT_TIMEOUT, $row->last_export_result_code );
		$this->assertSame( 'export_not_requested_before_grace_window', $row->last_export_error );
	}

	public function test_export_failure_updates_export_fields_distinct_from_ping_fields() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://export-fail.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordPingSuccess( $row, 202, Services::Request()->ts() + 600 );

		$repo->recordExportFailure( 'https://export-fail.example.com', SitesDB::EXPORT_RESULT_VERIFY_FAILED, 'verify failed' );

		$row = $repo->findById( $row->id, true );
		$this->assertSame( 202, $row->last_ping_http_code );
		$this->assertGreaterThan( 0, $row->last_ping_success_at );
		$this->assertGreaterThan( 0, $row->last_export_failure_at );
		$this->assertSame( SitesDB::EXPORT_RESULT_VERIFY_FAILED, $row->last_export_result_code );
		$this->assertSame( 'verify failed', $row->last_export_error );
	}

	public function test_export_endpoint_records_successful_slave_download_as_sync_success() :void {
		$con = $this->requireController();
		$url = 'https://export-success.example.com';
		$importID = 'export-success-id';
		$con->opts
			->optSet( 'importexport_whitelist', [ $url ] )
			->optSet( 'import_url_ids', [
				\hash( 'md5', $url ) => $importID,
			] )
			->store();
		$this->repo()->ensureLegacyImported( false );

		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [
				'url'    => $url,
				'id'     => $importID,
				'method' => 'json',
			] ),
		] );

		$ajaxFilter = static fn() :bool => true;
		$dieFilter = static function () {
			return static function () :void {
				throw new ImportExportSitesWpDieException();
			};
		};
		\add_filter( 'wp_doing_ajax', $ajaxFilter );
		\add_filter( 'wp_die_ajax_handler', $dieFilter );
		\ob_start();
		try {
			( new Export() )->toJson();
		}
		catch ( ImportExportSitesWpDieException $e ) {
		}
		finally {
			\ob_end_clean();
			\remove_filter( 'wp_die_ajax_handler', $dieFilter );
			\remove_filter( 'wp_doing_ajax', $ajaxFilter );
		}

		$row = $this->requireSite( $url );
		$this->assertGreaterThan( 0, $row->last_export_request_at );
		$this->assertGreaterThan( 0, $row->last_export_success_at );
		$this->assertSame( SitesDB::EXPORT_RESULT_SUCCESS, $row->last_export_result_code );
		$this->assertSame( 0, $row->last_ping_success_at );
	}

	public function test_manual_action_queues_only_selected_site() :void {
		$repo = $this->repo();
		$first = $repo->upsertActive( 'https://manual-one.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$second = $repo->upsertActive( 'https://manual-two.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordExportSuccess( $first->url, SitesDB::EXPORT_RESULT_SUCCESS );
		$repo->recordExportSuccess( $second->url, SitesDB::EXPORT_RESULT_SUCCESS );
		$this->assertSame( SitesDB::QUEUE_IDLE, $repo->findById( $first->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_IDLE, $repo->findById( $second->id, true )->queue_status );

		$action = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_QUEUE_SYNC,
			'rids'       => [ $second->id ],
		] );
		$method = new \ReflectionMethod( $action, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$first = $repo->findById( $first->id, true );
		$second = $repo->findById( $second->id, true );
		$this->assertSame( SitesDB::QUEUE_IDLE, $first->queue_status );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $second->queue_status );
		$this->assertTrue( $action->response()->payload()[ 'success' ] ?? false );
	}

	public function test_add_only_schema_alignment_preserves_populated_rows_and_extra_columns() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://schema.example.com', SitesDB::SOURCE_MANUAL, 'schema-id', true );
		$handler = $this->requireController()->db_con->import_export_sites;
		$table = $handler->getTable();
		$this->extraColumnTable = $table;

		global $wpdb;
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `extra_probe` varchar(32) NOT NULL DEFAULT ''" );
		Services::WpDb()->clearResultShowTables();
		$this->requireController()->db_con->loadDbH( $this->requireController()->db_con::MAP[ SitesDB::DB_KEY ][ 'slug' ], true );

		$this->assertSame( 'schema-id', $repo->findById( $row->id, true )->import_id );
		$this->assertContains( 'extra_probe', Services::WpDb()->getColumnsForTable( $table ) );
		$this->assertTrue( $this->requireController()->db_con->import_export_sites->isReady() );
	}

	private function repo() :SiteRepository {
		return new SiteRepository();
	}

	private function requireSite( string $url, bool $includeDeleted = false ) :Record {
		$row = $this->repo()->findByUrl( $url, $includeDeleted );
		$this->assertInstanceOf( Record::class, $row );
		return $row;
	}

	private function pushOldQueueUrls( array $urls ) :void {
		$queue = new WhitelistNotifyQueue( SiteRepository::OLD_QUEUE_ACTION, $this->requireController()->prefix() );
		foreach ( $urls as $url ) {
			$queue->push_to_queue( $url );
		}
		$queue->save();
	}

	private function clearOldQueueState() :void {
		try {
			( new WhitelistNotifyQueue( SiteRepository::OLD_QUEUE_ACTION, $this->requireController()->prefix() ) )->delete_all();
		}
		catch ( \Throwable $e ) {
		}
	}

	private function newImportExportSitesHandler( bool $useReadyCache ) :SitesDB {
		$con = $this->requireController();
		$dbDef = $con->db_con->getHandlers()[ SitesDB::DB_KEY ][ 'def' ];
		$dbDef[ 'table_prefix' ] = $con->getPluginPrefix( '_' );
		$handler = new SitesDB( $dbDef );
		$handler->use_table_ready_cache = $useReadyCache;
		return $handler;
	}

	private function clearImportExportSitesReadyCache() :void {
		try {
			SitesDB::GetTableReadyCache()->setReady( $this->newImportExportSitesHandler( false )->getTableSchema(), false );
		}
		catch ( \Throwable $e ) {
		}
		Services::WpDb()->clearResultShowTables();
	}

	private function dropImportExportSitesTable( bool $resetDbCon = true ) :void {
		global $wpdb;
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		Services::WpDb()->clearResultShowTables();
		if ( $resetDbCon ) {
			$this->requireController()->db_con->reset();
		}
	}
}

class ImportExportQueueRunnerTestDouble extends QueueRunner {

	private PingSender $sender;

	public function __construct( PingSender $sender ) {
		$this->sender = $sender;
	}

	protected function pingSender() :PingSender {
		return $this->sender;
	}
}

class ImportExportPingSenderTestDouble extends PingSender {

	private bool $success;
	private int $httpCode;
	private string $error;

	public function __construct( bool $success, int $httpCode, string $error ) {
		$this->success = $success;
		$this->httpCode = $httpCode;
		$this->error = $error;
	}

	public function send( string $url, int $timeout = 2 ) :array {
		return [
			'success'   => $this->success,
			'http_code' => $this->httpCode,
			'error'     => $this->error,
		];
	}
}

class ImportExportSitesExportRequestStub extends Request {

	public function __construct( array $queryData ) {
		parent::__construct();
		$this->query = $queryData;
		$this->post = [];
	}

	public function carbon( $setTimezone = false, bool $userLocale = true ) :Carbon {
		return Carbon::createFromTimestampUTC( $this->ts() );
	}

	public function ts( bool $update = true ) :int {
		return 1712620800;
	}
}

class ImportExportSitesWpDieException extends \RuntimeException {
}
