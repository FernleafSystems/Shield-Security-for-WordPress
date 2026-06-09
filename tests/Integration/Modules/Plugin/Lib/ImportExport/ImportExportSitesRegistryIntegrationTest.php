<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\ImportExport;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ImportExportSitesTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_Enable;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Ops\LoadConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates\HandleUpgrade;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\{
	PingSender,
	QueueRunner,
	QueueScheduler,
	SiteRepository
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\WhitelistNotifyQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForImportExportSites;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImportExportSites\{
	BuildImportExportSitesTableData,
	SiteSyncStatusBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\Request;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportSitesRegistryIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];
	private array $servicesSnapshot = [];
	private string $configStoreKey = '';
	private $storedConfigOptionSnapshot;
	private ?string $extraColumnTable = null;

	public function set_up() {
		parent::set_up();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'importexport_enable',
			'importexport_whitelist',
			'import_url_ids',
			'importexport_sites_migrated_at',
		] );
		$this->configStoreKey = 'aptoweb_controller_'.\substr( \hash( 'md5', \get_class( $this->requireController() ) ), 0, 6 );
		$this->storedConfigOptionSnapshot = Services::WpGeneral()->getOption( $this->configStoreKey );
		$this->requireDb( SitesDB::DB_KEY );
		$this->requireController()->opts
								  ->optSet( 'importexport_sites_migrated_at', 0 )
								  ->store();
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
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		if ( $this->storedConfigOptionSnapshot === false ) {
			Services::WpGeneral()->deleteOption( $this->configStoreKey );
		}
		else {
			Services::WpGeneral()->updateOption( $this->configStoreKey, $this->storedConfigOptionSnapshot );
		}
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
			[ 'https://slave-one.example.com', 'https://slave-one.example.com', 'https://slave-two.example.com' ],
			$con->opts->optGet( 'importexport_whitelist' )
		);
		$this->assertSame( 'import-one', $con->opts->optGet( 'import_url_ids' )[ \hash( 'md5', 'https://slave-one.example.com' ) ] ?? '' );
		$this->assertGreaterThan( 0, (int)$con->opts->optGet( 'importexport_sites_migrated_at' ) );
	}

	public function test_old_queue_only_marks_matching_active_fallback_urls_due() :void {
		$con = $this->requireController();
		$removed = $this->repo()->upsertActive( 'https://removed.example.com', SitesDB::SOURCE_MANUAL );
		$this->repo()->softDeleteUrl( $removed->url );
		$con->opts
			->optSet( 'importexport_whitelist', [
				'https://active.example.com',
			] )
			->store();
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
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_whitelist', [ $url ] )
			->optSet( 'import_url_ids', [
				\hash( 'md5', $url ) => 'upgrade-import-id',
			] )
			->store();
		$this->dropImportExportSitesTable();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );

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
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_scheduled_upgrade_imports_registry_without_scheduling_disabled_sync() :void {
		$con = $this->requireController();
		$previousVersion = $con->cfg->previous_version;
		$url = 'https://upgrade-import-disabled.example.com';
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_whitelist', [ $url ] )
			->optSet( 'import_url_ids', [
				\hash( 'md5', $url ) => 'upgrade-import-disabled-id',
			] )
			->store();
		$this->dropImportExportSitesTable();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );

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
		$this->assertSame( 'upgrade-import-disabled-id', $row->import_id );
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_config_rebuild_imports_legacy_settings_into_registry_without_upgrade_cron() :void {
		$con = $this->requireController();
		$previousRebuilt = $con->cfg->rebuilt;
		$url = 'https://config-rebuild-import.example.com';
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
			->optSet( 'importexport_whitelist', [ $url ] )
			->optSet( 'import_url_ids', [
				\hash( 'md5', $url ) => 'config-rebuild-import-id',
			] )
			->store();
		$this->dropImportExportSitesTable();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );

		try {
			$con->cfg->rebuilt = true;
			$this->runConfigRebuildImport();
		}
		finally {
			$con->cfg->rebuilt = $previousRebuilt;
		}

		$row = $this->requireSite( $url );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $row->status );
		$this->assertSame( 'config-rebuild-import-id', $row->import_id );
		$this->assertSame( [ $url ], $con->opts->optGet( 'importexport_whitelist' ) );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_config_rebuild_imports_registry_without_scheduling_disabled_sync() :void {
		$con = $this->requireController();
		$previousRebuilt = $con->cfg->rebuilt;
		$url = 'https://config-rebuild-disabled.example.com';
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_whitelist', [ $url ] )
			->optSet( 'import_url_ids', [
				\hash( 'md5', $url ) => 'config-rebuild-disabled-id',
			] )
			->store();
		$this->dropImportExportSitesTable();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );

		try {
			$con->cfg->rebuilt = true;
			$this->runConfigRebuildImport();
		}
		finally {
			$con->cfg->rebuilt = $previousRebuilt;
		}

		$row = $this->requireSite( $url );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $row->status );
		$this->assertSame( 'config-rebuild-disabled-id', $row->import_id );
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_same_version_config_signature_rebuild_imports_legacy_settings_into_registry() :void {
		$con = $this->requireController();
		$stored = $con->cfg->getRawData();
		$stored[ 'hash' ] = 'stale-signature';
		$stored[ 'properties' ][ 'version' ] = $con->cfg->properties[ 'version' ];
		Services::WpGeneral()->updateOption( $this->configStoreKey, $stored );

		$cfg = ( new LoadConfig( $con->paths->forPluginItem( 'plugin.json' ), $this->configStoreKey ) )->run();

		$this->assertTrue( $cfg->rebuilt );
		$this->assertSame( $con->cfg->properties[ 'version' ], $cfg->properties[ 'version' ] );

		$previousRebuilt = $con->cfg->rebuilt;
		$url = 'https://same-version-rebuild.example.com';
		$con->opts
			->optSet( 'importexport_whitelist', [ $url ] )
			->optSet( 'import_url_ids', [
				\hash( 'md5', $url ) => 'same-version-rebuild-id',
			] )
			->store();
		$this->dropImportExportSitesTable();

		try {
			$con->cfg->rebuilt = $cfg->rebuilt;
			$this->runConfigRebuildImport();
		}
		finally {
			$con->cfg->rebuilt = $previousRebuilt;
		}

		$row = $this->requireSite( $url );
		$this->assertSame( SitesDB::STATUS_ACTIVE, $row->status );
		$this->assertSame( 'same-version-rebuild-id', $row->import_id );
	}

	public function test_legacy_import_does_not_rewrite_existing_active_rows_when_nothing_changes() :void {
		$con = $this->requireController();
		$url = 'https://idempotent-legacy-import.example.com';
		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], 1712620800 ),
		] );
		$con->opts
			->optSet( 'importexport_whitelist', [ $url ] )
			->optSet( 'import_url_ids', [] )
			->store();

		$this->repo()->ensureLegacyImported( false );
		$row = $this->requireSite( $url );
		$migratedAt = (int)$con->opts->optGet( 'importexport_sites_migrated_at' );

		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], 1712707200 ),
		] );
		$this->repo()->ensureLegacyImported( false );

		$after = $this->requireSite( $url );
		$this->assertSame( $row->updated_at, $after->updated_at );
		$this->assertSame( $migratedAt, (int)$con->opts->optGet( 'importexport_sites_migrated_at' ) );
	}

	/**
	 * @dataProvider importExportSitesBatchEdgeCountProvider
	 */
	public function test_legacy_import_batches_edge_counts( int $count ) :void {
		$urls = $this->generatedImportExportUrls( $count, 'batch-import' );
		$urlIds = $this->importIdsForUrls( $urls, 'batch-import-id' );
		$this->setLegacyImportOptions( $urls, $urlIds );

		$queries = $this->captureImportExportSiteQueries( function () :void {
			$this->repo()->ensureLegacyImported( false );
		} );

		$this->assertCount( $count, $this->repo()->selectActiveRows() );
		foreach ( $urls as $position => $url ) {
			$this->assertSame(
				$this->importIdAtPosition( 'batch-import-id', $position + 1 ),
				$this->requireSite( $url )->import_id
			);
		}
		$expectedChunks = (int)\ceil( $count/20 );
		$this->assertSame( $expectedChunks, $this->queryFamilyCount( $queries, 'select_by_hashes' ) );
		$this->assertSame( $expectedChunks, $this->queryFamilyCount( $queries, 'insert_ignore' ) );
		$this->assertSame( 0, $this->queryFamilyCount( $queries, 'case_update' ) );
	}

	public function test_legacy_import_handles_three_hundred_sites_in_bounded_sql_chunks() :void {
		$urls = $this->generatedImportExportUrls( 300, 'large-import' );
		$urlIds = $this->importIdsForUrls( $urls, 'large-import-id' );
		$this->setLegacyImportOptions( $urls, $urlIds );

		$queries = $this->captureImportExportSiteQueries( function () :void {
			$this->repo()->ensureLegacyImported( false );
		} );

		$this->assertCount( 300, $this->repo()->selectActiveRows() );
		$this->assertSame( $this->importIdAtPosition( 'large-import-id', 1 ), $this->requireSite( $urls[ 0 ] )->import_id );
		$this->assertSame( $this->importIdAtPosition( 'large-import-id', 150 ), $this->requireSite( $urls[ 149 ] )->import_id );
		$this->assertSame( $this->importIdAtPosition( 'large-import-id', 300 ), $this->requireSite( $urls[ 299 ] )->import_id );
		$this->assertSame( 15, $this->queryFamilyCount( $queries, 'select_by_hashes' ) );
		$this->assertSame( 15, $this->queryFamilyCount( $queries, 'insert_ignore' ) );
		$this->assertSame( 0, $this->queryFamilyCount( $queries, 'case_update' ) );
		$this->assertSame( 0, $this->queryFamilyCount( $queries, 'select_active' ) );
	}

	public function test_legacy_import_mixes_unchanged_changed_deleted_and_missing_rows_in_chunks() :void {
		$repo = $this->repo();
		$urls = $this->generatedImportExportUrls( 21, 'mixed-import' );
		$urlIds = $this->importIdsForUrls( $urls, 'mixed-import-id' );
		$unchangedUrls = \array_slice( $urls, 0, 5 );
		$changedUrls = \array_slice( $urls, 5, 5 );
		$deletedUrls = \array_slice( $urls, 10, 5 );
		$missingUrls = \array_slice( $urls, 15, 6 );

		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], 1712620800 ),
		] );
		foreach ( $unchangedUrls as $position => $url ) {
			$repo->upsertActive( $url, SitesDB::SOURCE_MANUAL, $this->importIdAtPosition( 'mixed-import-id', $position + 1 ) );
		}
		foreach ( $changedUrls as $url ) {
			$repo->upsertActive( $url, SitesDB::SOURCE_MANUAL, 'old-import-id' );
		}
		foreach ( $deletedUrls as $url ) {
			$repo->upsertActive( $url, SitesDB::SOURCE_MANUAL, 'deleted-import-id' );
			$repo->softDeleteUrl( $url );
		}
		$unchangedRows = [];
		foreach ( $unchangedUrls as $url ) {
			$unchangedRows[ $url ] = $this->requireSite( $url, true );
		}

		$this->setLegacyImportOptions( $urls, $urlIds );
		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], 1712707200 ),
		] );

		$queries = $this->captureImportExportSiteQueries( function () use ( $repo ) :void {
			$repo->ensureLegacyImported( false );
		} );

		foreach ( $unchangedUrls as $url ) {
			$row = $this->requireSite( $url );
			$this->assertSame( SitesDB::STATUS_ACTIVE, $row->status );
			$this->assertSame( $unchangedRows[ $url ]->updated_at, $row->updated_at );
		}
		foreach ( $changedUrls as $url ) {
			$this->assertSame( $urlIds[ \hash( 'md5', $url ) ], $this->requireSite( $url )->import_id );
		}
		foreach ( $deletedUrls as $url ) {
			$row = $this->requireSite( $url, true );
			$this->assertSame( SitesDB::STATUS_ACTIVE, $row->status );
			$this->assertSame( 0, $row->deleted_at );
			$this->assertSame( $urlIds[ \hash( 'md5', $url ) ], $row->import_id );
		}
		foreach ( $missingUrls as $url ) {
			$this->assertSame( SitesDB::STATUS_ACTIVE, $this->requireSite( $url )->status );
		}

		$this->assertSame( 2, $this->queryFamilyCount( $queries, 'select_by_hashes' ) );
		$this->assertSame( 1, $this->queryFamilyCount( $queries, 'insert_ignore' ) );
		$this->assertSame( 1, $this->queryFamilyCount( $queries, 'case_update' ) );
		$updateSql = $this->querySqlForFamily( $queries, 'case_update' );
		foreach ( $unchangedUrls as $url ) {
			$this->assertStringNotContainsString( \hash( 'md5', $url ), $updateSql );
		}
		foreach ( \array_merge( $changedUrls, $deletedUrls ) as $url ) {
			$this->assertStringContainsString( \hash( 'md5', $url ), $updateSql );
		}
	}

	/**
	 * @dataProvider importExportSitesBatchEdgeCountProvider
	 */
	public function test_find_by_urls_batches_edge_counts( int $count ) :void {
		$repo = $this->repo();
		$urls = $this->generatedImportExportUrls( $count, 'lookup-batch' );
		foreach ( $urls as $url ) {
			$repo->upsertActive( $url, SitesDB::SOURCE_MANUAL );
		}

		$found = [];
		$queries = $this->captureImportExportSiteQueries( function () use ( $repo, $urls, &$found ) :void {
			$found = $repo->findByUrls( $urls );
		} );

		$this->assertCount( $count, $found );
		foreach ( $urls as $url ) {
			$this->assertArrayHasKey( $url, $found );
		}
		$this->assertSame( (int)\ceil( $count/20 ), $this->queryFamilyCount( $queries, 'select_by_hashes' ) );
	}

	public function test_find_by_urls_ignores_invalid_duplicates_and_excludes_deleted_by_default() :void {
		$repo = $this->repo();
		$activeUrl = 'https://lookup-active.example.com';
		$deletedUrl = 'https://lookup-deleted.example.com';
		$repo->upsertActive( $activeUrl, SitesDB::SOURCE_MANUAL );
		$repo->upsertActive( $deletedUrl, SitesDB::SOURCE_MANUAL );
		$repo->softDeleteUrl( $deletedUrl );

		$found = $repo->findByUrls( [
			'not-a-url',
			$activeUrl,
			$activeUrl,
			'',
			$deletedUrl,
		] );
		$this->assertSame( [ $activeUrl ], \array_keys( $found ) );

		$withDeleted = $repo->findByUrls( [ $activeUrl, $deletedUrl ], true );
		$this->assertArrayHasKey( $activeUrl, $withDeleted );
		$this->assertArrayHasKey( $deletedUrl, $withDeleted );
		$this->assertSame( SitesDB::STATUS_DELETED, $withDeleted[ $deletedUrl ]->status );
	}

	public function test_queue_site_ids_batches_active_rows_and_ignores_deleted_or_missing_ids() :void {
		$repo = $this->repo();
		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], 1712620800 ),
		] );
		$activeIds = [];
		foreach ( $this->generatedImportExportUrls( 21, 'queue-selected' ) as $url ) {
			$activeIds[] = $repo->upsertActive( $url, SitesDB::SOURCE_MANUAL, '', true )->id;
		}
		$deleted = $repo->upsertActive( 'https://queue-selected-deleted.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->softDeleteUrl( $deleted->url );

		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], 1712707200 ),
		] );
		$queuedCount = 0;
		$queries = $this->captureImportExportSiteQueries( function () use ( $repo, $activeIds, $deleted, &$queuedCount ) :void {
			$queuedCount = $repo->queueSiteIds( \array_merge( $activeIds, [ $deleted->id, 9999999 ] ) );
		} );

		$this->assertSame( 21, $queuedCount );
		foreach ( $activeIds as $id ) {
			$row = $repo->findById( $id, true );
			$this->assertSame( SitesDB::QUEUE_QUEUED, $row->queue_status );
			$this->assertSame( 1712707200, $row->queued_at );
			$this->assertSame( 1712707200, $row->next_ping_at );
		}
		$deleted = $repo->findById( $deleted->id, true );
		$this->assertSame( SitesDB::STATUS_DELETED, $deleted->status );
		$this->assertSame( SitesDB::QUEUE_IDLE, $deleted->queue_status );
		$this->assertSame( 2, $this->queryFamilyCount( $queries, 'queue_update' ) );
	}

	public function test_claim_due_rows_batches_claim_updates_and_refreshes_returned_rows_in_memory() :void {
		$repo = $this->repo();
		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], 1712620800 ),
		] );
		foreach ( $this->generatedImportExportUrls( 21, 'claim-due' ) as $url ) {
			$repo->upsertActive( $url, SitesDB::SOURCE_MANUAL, '', true );
		}

		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], 1712707200 ),
		] );
		$claimedRows = [];
		$queries = $this->captureImportExportSiteQueries( function () use ( $repo, &$claimedRows ) :void {
			$claimedRows = $repo->claimDueRows( 21, 1712707800 );
		} );

		$this->assertCount( 21, $claimedRows );
		foreach ( $claimedRows as $row ) {
			$this->assertSame( SitesDB::QUEUE_PROCESSING, $row->queue_status );
			$this->assertSame( 1712707200, $row->picked_at );
			$this->assertSame( 1712707800, $row->lock_until );
			$persisted = $repo->findById( $row->id, true );
			$this->assertSame( SitesDB::QUEUE_PROCESSING, $persisted->queue_status );
			$this->assertSame( 1712707200, $persisted->picked_at );
			$this->assertSame( 1712707800, $persisted->lock_until );
		}
		$this->assertSame( 2, $this->queryFamilyCount( $queries, 'claim_update' ) );
	}

	public function test_legacy_import_does_not_repeat_after_migrated_at_even_when_legacy_inputs_change() :void {
		$con = $this->requireController();
		$first = 'https://same-request-import-one.example.com';
		$second = 'https://same-request-import-two.example.com';
		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], 1712620800 ),
		] );
		$con->opts
			->optSet( 'importexport_whitelist', [ $first ] )
			->optSet( 'import_url_ids', [
				\hash( 'md5', $first ) => 'same-request-one-id',
			] )
			->store();

		$repo = $this->repo();
		$repo->ensureLegacyImported();
		$firstRow = $this->requireSite( $first );
		$con->db_con->import_export_sites->getQueryUpdater()->updateById( $firstRow->id, [
			'status'       => SitesDB::STATUS_DELETED,
			'queue_status' => SitesDB::QUEUE_IDLE,
			'deleted_at'   => 1712620801,
		] );

		$repo->ensureLegacyImported();

		$this->assertNull( $repo->findByUrl( $first ) );

		$con->opts
			->optSet( 'importexport_whitelist', [ $first, $second ] )
			->store();
		$repo->ensureLegacyImported();

		$this->assertNull( $repo->findByUrl( $first ) );
		$this->assertNull( $repo->findByUrl( $second, true ) );
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
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );
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
		$payload = $action->response()->payload();

		$this->assertSame( SitesDB::QUEUE_IDLE, $first->queue_status );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $second->queue_status );
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_manual_delete_action_hard_deletes_only_selected_site() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		$repo = $this->repo();
		$first = $repo->upsertActive( 'https://delete-keep.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$second = $repo->upsertActive( 'https://delete-remove.example.com', SitesDB::SOURCE_MANUAL, '', true );

		$action = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_DELETE_SITE,
			'rids'       => [ $second->id ],
		] );
		$method = new \ReflectionMethod( $action, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$payload = $action->response()->payload();

		$this->assertInstanceOf( Record::class, $repo->findById( $first->id, true ) );
		$this->assertNull( $repo->findById( $second->id, true ) );
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertArrayHasKey( 'table_reload', $payload );
		$this->assertArrayHasKey( 'page_reload', $payload );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertTrue( $payload[ 'table_reload' ] );
		$this->assertFalse( $payload[ 'page_reload' ] );
	}

	public function test_manual_delete_action_reloads_page_when_final_site_is_removed() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		$row = $this->repo()->upsertActive( 'https://delete-final.example.com', SitesDB::SOURCE_MANUAL, '', true );

		$action = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_DELETE_SITE,
			'rids'       => [ $row->id ],
		] );
		$method = new \ReflectionMethod( $action, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$payload = $action->response()->payload();

		$this->assertNull( $this->repo()->findById( $row->id, true ) );
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertArrayHasKey( 'table_reload', $payload );
		$this->assertArrayHasKey( 'page_reload', $payload );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertFalse( $payload[ 'table_reload' ] );
		$this->assertTrue( $payload[ 'page_reload' ] );
	}

	public function test_manual_queue_action_rejects_disabled_import_export_without_scheduling() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://manual-disabled.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordExportSuccess( $row->url, SitesDB::EXPORT_RESULT_SUCCESS );

		$action = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_QUEUE_SYNC,
			'rids'       => [ $row->id ],
		] );
		$method = new \ReflectionMethod( $action, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );

		$row = $repo->findById( $row->id, true );
		$payload = $action->response()->payload();

		$this->assertSame( SitesDB::QUEUE_IDLE, $row->queue_status );
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertFalse( $payload[ 'success' ] );
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_controller_queues_all_active_sites_and_schedules_when_enabled() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );
		$repo = $this->repo();
		$first = $repo->upsertActive( 'https://all-active-one.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$second = $repo->upsertActive( 'https://all-active-two.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordExportSuccess( $first->url, SitesDB::EXPORT_RESULT_SUCCESS );
		$repo->recordExportSuccess( $second->url, SitesDB::EXPORT_RESULT_SUCCESS );

		$count = ( new ImportExportController() )->queueAllActiveSitesForSync();

		$this->assertSame( 2, $count );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $first->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $second->id, true )->queue_status );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_controller_rejects_queue_all_active_when_disabled_without_scheduling() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://all-active-disabled.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordExportSuccess( $row->url, SitesDB::EXPORT_RESULT_SUCCESS );

		try {
			( new ImportExportController() )->queueAllActiveSitesForSync();
			$this->fail( 'Expected disabled import/export queue-all to fail.' );
		}
		catch ( \RuntimeException $e ) {
			$this->assertSame( 'Import and export is not enabled.', $e->getMessage() );
		}

		$this->assertSame( SitesDB::QUEUE_IDLE, $repo->findById( $row->id, true )->queue_status );
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_enable_action_turns_on_import_export_and_schedules_queue() :void {
		$this->loginAsSecurityAdmin();
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con = $this->requireController();
		$url = 'https://enable-action.example.com';
		$con->opts
			->optSet( 'importexport_enable', 'N' )
			->optSet( 'importexport_whitelist', [ $url ] )
			->optSet( 'import_url_ids', [
				\hash( 'md5', $url ) => 'enable-action-id',
			] )
			->store();
		$this->pushOldQueueUrls( [ $url ] );
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );

		$payload = ( new ActionProcessor() )->processAction( PluginImportExport_Enable::SLUG )->payload();

		$this->assertArrayHasKey( 'success', $payload );
		$this->assertArrayHasKey( 'page_reload', $payload );
		$this->assertTrue( (bool)$payload[ 'success' ] );
		$this->assertTrue( (bool)$payload[ 'page_reload' ] );
		$this->assertSame( 'Y', (string)$con->opts->optGet( 'importexport_enable' ) );
		$row = $this->requireSite( $url );
		$this->assertSame( 'enable-action-id', $row->import_id );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $row->queue_status );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_queue_scheduler_registers_callback_without_scheduling_disabled_sync() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();
		$scheduler = new QueueScheduler( static fn() :bool => false );
		$hook = $scheduler->hook();
		\remove_all_actions( $hook );
		\wp_clear_scheduled_hook( $hook );

		try {
			$scheduler->setup();

			$this->assertNotFalse( \has_action( $hook ) );
			$this->assertFalse( \wp_next_scheduled( $hook ) );

			\wp_schedule_single_event( Services::Request()->ts() + 30, $hook );
			$this->assertNotFalse( \wp_next_scheduled( $hook ) );

			do_action( $hook );

			$this->assertFalse( \wp_next_scheduled( $hook ) );
		}
		finally {
			\remove_all_actions( $hook );
			\wp_clear_scheduled_hook( $hook );
		}
	}

	public function test_controller_registers_queue_scheduler_when_sync_is_available() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		$scheduler = new QueueScheduler();
		$hook = $scheduler->hook();
		\remove_all_actions( $hook );
		\wp_clear_scheduled_hook( $hook );

		try {
			( new ImportExportController() )->execute();

			$this->assertNotFalse( \has_action( $hook ) );
			$this->assertNotFalse( \wp_next_scheduled( $hook ) );
		}
		finally {
			\remove_all_actions( $hook );
			\wp_clear_scheduled_hook( $hook );
		}
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

	public function test_table_search_panes_filter_rows_and_counts_with_text_search() :void {
		$ids = $this->seedSearchPaneImportExportSites();

		$problem = $this->retrieveImportExportSitesTableData( 'sync-pane-filter', [
			'sync_state' => [ SiteSyncStatusBuilder::STATE_PROBLEM ],
		] );
		$this->assertSame( 1, (int)$problem[ 'recordsFiltered' ] );
		$this->assertSame( [ $ids[ 'problem' ] ], \array_column( $problem[ 'data' ], 'rid' ) );

		$deleted = $this->retrieveImportExportSitesTableData( 'sync-pane-filter', [
			'status_key' => [ SitesDB::STATUS_DELETED ],
		] );
		$this->assertSame( 1, (int)$deleted[ 'recordsFiltered' ] );
		$this->assertSame( [ $ids[ 'deleted' ] ], \array_column( $deleted[ 'data' ], 'rid' ) );

		$queued = $this->retrieveImportExportSitesTableData( 'sync-pane-filter', [
			'queue_status_key' => [ SitesDB::QUEUE_QUEUED ],
		] );
		$queuedIds = \array_column( $queued[ 'data' ], 'rid' );
		\sort( $queuedIds );
		$expectedQueued = [ $ids[ 'pending' ], $ids[ 'problem' ] ];
		\sort( $expectedQueued );
		$this->assertSame( 2, (int)$queued[ 'recordsFiltered' ] );
		$this->assertSame( $expectedQueued, $queuedIds );

		$mismatch = $this->retrieveImportExportSitesTableData( 'sync-pane-filter-pending', [
			'sync_state' => [ SiteSyncStatusBuilder::STATE_PROBLEM ],
		] );
		$this->assertSame( 0, (int)$mismatch[ 'recordsFiltered' ] );
		$this->assertSame( [], $mismatch[ 'data' ] );

		$invalid = $this->retrieveImportExportSitesTableData( 'sync-pane-filter', [
			'sync_state'       => [ 'bad-state' ],
			'status_key'       => [ 'bad-status' ],
			'queue_status_key' => [ 'bad-queue' ],
		] );
		$allIds = \array_column( $invalid[ 'data' ], 'rid' );
		\sort( $allIds );
		$expectedAll = \array_values( $ids );
		\sort( $expectedAll );
		$this->assertSame( 4, (int)$invalid[ 'recordsFiltered' ] );
		$this->assertSame( $expectedAll, $allIds );
	}

	private function repo() :SiteRepository {
		return new SiteRepository();
	}

	private function seedSearchPaneImportExportSites() :array {
		$repo = $this->repo();
		$working = $repo->upsertActive( 'https://sync-pane-filter-working.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordExportSuccess( $working->url, SitesDB::EXPORT_RESULT_SUCCESS );

		$problem = $repo->upsertActive( 'https://sync-pane-filter-problem.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordPingFailure( $problem, 503, 'service unavailable' );

		$pending = $repo->upsertActive( 'https://sync-pane-filter-pending.example.com', SitesDB::SOURCE_MANUAL, '', true );

		$deleted = $repo->upsertActive( 'https://sync-pane-filter-deleted.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->softDeleteUrl( $deleted->url );
		$deleted = $repo->findById( $deleted->id, true );

		return [
			'working' => $working->id,
			'problem' => $problem->id,
			'pending' => $pending->id,
			'deleted' => $deleted->id,
		];
	}

	private function retrieveImportExportSitesTableData( string $search, array $searchPanes ) :array {
		\delete_transient( 'shield_dt_total_'.\md5( BuildImportExportSitesTableData::class ) );

		$builder = new BuildImportExportSitesTableData();
		$builder->table_data = $this->buildImportExportSitesTableDataRequest( $search, [
			'searchPanes' => $searchPanes,
		] );

		return $builder->build();
	}

	private function buildImportExportSitesTableDataRequest( string $search = '', array $overrides = [] ) :array {
		$tableData = ( new ForImportExportSites() )->buildRaw();
		$tableData[ 'order' ] = \array_values( \array_map(
			static fn( array $order ) :array => [
				'column' => (int)( $order[ 0 ] ?? 0 ),
				'dir'    => (string)( $order[ 1 ] ?? 'desc' ),
			],
			\is_array( $tableData[ 'order' ] ?? null ) ? $tableData[ 'order' ] : []
		) );

		return \array_merge( $tableData, [
			'draw'   => 1,
			'start'  => 0,
			'length' => 25,
			'search' => [
				'value' => $search,
				'regex' => false,
			],
		], $overrides );
	}

	private function requireSite( string $url, bool $includeDeleted = false ) :Record {
		$row = $this->repo()->findByUrl( $url, $includeDeleted );
		$this->assertInstanceOf( Record::class, $row );
		return $row;
	}

	public static function importExportSitesBatchEdgeCountProvider() :array {
		return [
			'zero'       => [ 0 ],
			'one'        => [ 1 ],
			'nineteen'   => [ 19 ],
			'twenty'     => [ 20 ],
			'twenty-one' => [ 21 ],
		];
	}

	private function generatedImportExportUrls( int $count, string $prefix ) :array {
		$urls = [];
		for ( $i = 1; $i <= $count; $i++ ) {
			$urls[] = \sprintf( 'https://%s-%03d.example.com', $prefix, $i );
		}
		return $urls;
	}

	private function importIdsForUrls( array $urls, string $prefix ) :array {
		$urlIds = [];
		foreach ( $urls as $position => $url ) {
			$urlIds[ \hash( 'md5', $url ) ] = $this->importIdAtPosition( $prefix, $position + 1 );
		}
		return $urlIds;
	}

	private function importIdAtPosition( string $prefix, int $position ) :string {
		return \sprintf( '%s-%03d', $prefix, $position );
	}

	private function setLegacyImportOptions( array $urls, array $urlIds = [] ) :void {
		$this->requireController()->opts
			->optSet( 'importexport_whitelist', $urls )
			->optSet( 'import_url_ids', $urlIds )
			->store();
	}

	private function captureImportExportSiteQueries( callable $callback ) :array {
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$queries = [];
		$filter = function ( $query ) use ( $table, &$queries ) {
			$query = (string)$query;
			if ( \stripos( $query, $table ) !== false ) {
				$family = $this->classifyImportExportSiteQuery( $query );
				if ( !empty( $family ) ) {
					$queries[] = [
						'family' => $family,
						'sql'    => $this->compactSql( $query ),
					];
				}
			}
			return $query;
		};

		\add_filter( 'query', $filter, \PHP_INT_MAX, 1 );
		try {
			$callback();
		}
		finally {
			\remove_filter( 'query', $filter, \PHP_INT_MAX );
		}

		return $queries;
	}

	private function classifyImportExportSiteQuery( string $query ) :?string {
		$compact = \strtoupper( $this->compactSql( $query ) );
		if ( \strpos( $compact, 'SELECT ' ) === 0 && \strpos( $compact, '`URL_HASH` IN' ) !== false ) {
			return 'select_by_hashes';
		}
		if ( \strpos( $compact, 'SELECT ' ) === 0
			 && \strpos( $compact, '`URL_HASH` IN' ) === false
			 && \preg_match( "/`STATUS`\\s*=\\s*'ACTIVE'/", $compact ) === 1 ) {
			return 'select_active';
		}
		if ( \strpos( $compact, 'INSERT IGNORE INTO ' ) === 0 ) {
			return 'insert_ignore';
		}
		if ( \strpos( $compact, 'UPDATE ' ) === 0 && \strpos( $compact, 'CASE `URL_HASH`' ) !== false ) {
			return 'case_update';
		}
		if ( \strpos( $compact, 'UPDATE ' ) === 0
			 && \preg_match( "/`QUEUE_STATUS`\\s*=\\s*'QUEUED'/", $compact ) === 1 ) {
			return 'queue_update';
		}
		if ( \strpos( $compact, 'UPDATE ' ) === 0
			 && \preg_match( "/`QUEUE_STATUS`\\s*=\\s*'PROCESSING'/", $compact ) === 1 ) {
			return 'claim_update';
		}

		return null;
	}

	private function queryFamilyCount( array $queries, string $family ) :int {
		return \count( \array_filter( $queries, static fn( array $query ) :bool => $query[ 'family' ] === $family ) );
	}

	private function querySqlForFamily( array $queries, string $family ) :string {
		return \implode( "\n", \array_map(
			static fn( array $query ) :string => $query[ 'sql' ],
			\array_filter( $queries, static fn( array $query ) :bool => $query[ 'family' ] === $family )
		) );
	}

	private function compactSql( string $query ) :string {
		return (string)\preg_replace( '/\s+/', ' ', \trim( $query ) );
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

	private function runConfigRebuildImport() :void {
		$method = new \ReflectionMethod( $this->requireController(), 'importExportSitesRegistryOnConfigRebuild' );
		$method->setAccessible( true );
		$method->invoke( $this->requireController() );
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

	private int $timestamp = 1712620800;

	public function __construct( array $queryData, int $timestamp = 1712620800 ) {
		$this->timestamp = $timestamp;
		parent::__construct();
		$this->query = $queryData;
		$this->post = [];
	}

	public function carbon( $setTimezone = false, bool $userLocale = true ) :Carbon {
		return Carbon::createFromTimestampUTC( $this->ts() );
	}

	public function ts( bool $update = true ) :int {
		return $this->timestamp;
	}
}

class ImportExportSitesWpDieException extends \RuntimeException {
}
