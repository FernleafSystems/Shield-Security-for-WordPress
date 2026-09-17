<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib\ImportExport;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Common\TableReadyCache;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ImportExportSitesTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_Enable;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Ops\LoadConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates\HandleUpgrade;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Handler as ProfilesDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Export;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Diagnostics\SyncObservation;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\ProfileRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\{
	InvitationMetadata,
	NotificationMetadata,
	PingSender,
	QueueProcessor,
	QueueScheduler,
	SiteRepository,
	SyncSiteInviteSender
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\WhitelistNotifyQueue;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForImportExportSites;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImportExportSites\{
	BuildImportExportSitesTableData,
	SiteSyncStatusBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\{
	RuntimeTestState,
	ServicesState
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\Request;
use FernleafSystems\Wordpress\Services\Services;
use PHPUnit\Framework\Attributes\DataProvider;

class ImportExportSitesRegistryIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];
	private array $servicesSnapshot = [];
	private string $configStoreKey = '';
	private $storedConfigOptionSnapshot;
	private bool $persistentStateRestored = false;

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
		$this->requireDb( ProfilesDB::DB_KEY );
		$this->requireDb( SitesDB::DB_KEY );
		$this->requireController()->opts
								  ->optSet( 'importexport_sites_migrated_at', 0 )
								  ->store();
		$this->clearOldQueueState();
	}

	public function tear_down() {
		if ( !$this->persistentStateRestored ) {
			$this->clearImportExportSitesReadyCache();
			$this->clearOldQueueState();
			\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );
		}
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		$this->restoreStoredConfigOptionSnapshot();
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

	public function test_delete_by_ids_uses_bounded_batches_and_counts_existing_rows() :void {
		$repo = $this->repo();
		$ids = [];
		foreach ( \range( 1, 21 ) as $position ) {
			$ids[] = $repo->upsertActive(
				"https://delete-batch-{$position}.example.com",
				SitesDB::SOURCE_MANUAL
			)->id;
		}

		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$deletes = 0;
		$selects = 0;
		$filter = static function ( string $query ) use ( $table, &$deletes, &$selects ) :string {
			if ( \strpos( $query, "DELETE FROM `{$table}` WHERE `id` IN (" ) === 0 ) {
				$deletes++;
			}
			elseif ( \strpos( $query, "SELECT * FROM `{$table}`" ) === 0 ) {
				$selects++;
			}
			return $query;
		};
		\add_filter( 'query', $filter, 1000 );
		try {
			$deleted = $repo->deleteByIds( \array_merge( $ids, [ $ids[ 0 ], 0, -1, 9999999 ] ) );
		}
		finally {
			\remove_filter( 'query', $filter, 1000 );
		}

		$this->assertSame( 21, $deleted );
		$this->assertSame( 2, $deletes );
		$this->assertSame( 0, $selects );
		foreach ( $ids as $id ) {
			$this->assertNull( $repo->findById( $id, true ) );
		}
	}

	public function test_legacy_import_insert_failure_preserves_marker_and_old_queue_for_retry() :void {
		$url = 'https://legacy-insert-retry.example.com';
		$this->setLegacyImportOptions( [ $url ] );
		$this->pushOldQueueUrls( [ $url ] );

		$this->runWithFailedImportExportSiteQuery( 'insert_ignore', function () :void {
			$this->repo()->ensureLegacyImported();
		} );

		$this->assertSame( 0, (int)$this->requireController()->opts->optGet( SiteRepository::MIGRATED_AT_OPTION ) );
		$this->assertNotEmpty( ( new WhitelistNotifyQueue( SiteRepository::OLD_QUEUE_ACTION, $this->requireController()->prefix() ) )->get_batches() );
		$this->assertNull( $this->repo()->findByUrl( $url, true ) );

		$this->repo()->ensureLegacyImported();

		$this->assertInstanceOf( Record::class, $this->repo()->findByUrl( $url ) );
		$this->assertGreaterThan( 0, (int)$this->requireController()->opts->optGet( SiteRepository::MIGRATED_AT_OPTION ) );
		$this->assertSame( [], ( new WhitelistNotifyQueue( SiteRepository::OLD_QUEUE_ACTION, $this->requireController()->prefix() ) )->get_batches() );
	}

	public function test_legacy_import_update_failure_preserves_marker_and_old_queue_for_retry() :void {
		$url = 'https://legacy-update-retry.example.com';
		$row = $this->repo()->upsertActive( $url, SitesDB::SOURCE_MANUAL );
		$this->assertInstanceOf( Record::class, $row );
		$this->repo()->softDeleteUrl( $url );
		$this->setLegacyImportOptions( [ $url ] );
		$this->pushOldQueueUrls( [ $url ] );

		$this->runWithFailedImportExportSiteQuery( 'case_update', function () :void {
			$this->repo()->ensureLegacyImported();
		} );

		$this->assertSame( 0, (int)$this->requireController()->opts->optGet( SiteRepository::MIGRATED_AT_OPTION ) );
		$this->assertNotEmpty( ( new WhitelistNotifyQueue( SiteRepository::OLD_QUEUE_ACTION, $this->requireController()->prefix() ) )->get_batches() );
		$this->assertSame( SitesDB::STATUS_DELETED, $this->repo()->findByUrl( $url, true )->status );

		$this->repo()->ensureLegacyImported();

		$this->assertSame( SitesDB::STATUS_ACTIVE, $this->repo()->findByUrl( $url )->status );
		$this->assertGreaterThan( 0, (int)$this->requireController()->opts->optGet( SiteRepository::MIGRATED_AT_OPTION ) );
		$this->assertSame( [], ( new WhitelistNotifyQueue( SiteRepository::OLD_QUEUE_ACTION, $this->requireController()->prefix() ) )->get_batches() );
	}

	/** @group database-transaction-exception */
	public function test_registry_repairs_from_fallback_after_table_loss() :void {
		$this->runWithImportExportSitesPersistentMutation( function () :void {
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
		} );
	}

	/** @group database-transaction-exception */
	public function test_registry_repairs_from_fallback_after_warm_ready_cache_table_loss() :void {
		$this->runWithImportExportSitesPersistentMutation( function () :void {
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
		} );
	}

	/** @group database-transaction-exception */
	public function test_scheduled_upgrade_imports_legacy_settings_into_registry() :void {
		$this->runWithSeededCronPreservationCheck(
			$this->requireController()->prefix( 'integration-test-upgrade-import-enabled-cron-owner' ),
			function () :void {
				$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
				$this->runWithImportExportSitesPersistentMutation( function () :void {
					$con = $this->requireController();
					$url = 'https://upgrade-import.example.com';
					$con->opts
						->optSet( 'importexport_enable', 'Y' )
						->optSet( 'importexport_whitelist', [ $url ] )
						->optSet( 'import_url_ids', [
							\hash( 'md5', $url ) => 'upgrade-import-id',
						] )
						->store();
					$this->dropImportExportSitesTable();
					\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );

					$con->cfg->previous_version = '0.0.1';
					( new HandleUpgrade() )->execute();
					do_action( $con->prefix( 'plugin-upgrade' ), '0.0.1' );

					$row = $this->requireSite( $url );
					$this->assertSame( SitesDB::STATUS_ACTIVE, $row->status );
					$this->assertSame( 'upgrade-import-id', $row->import_id );
					$this->assertSame( [ $url ], $con->opts->optGet( 'importexport_whitelist' ) );
					$this->assertSame( 'upgrade-import-id', $con->opts->optGet( 'import_url_ids' )[ \hash( 'md5', $url ) ] ?? '' );
					$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
				}, true );
			}
		);
	}

	/** @group database-transaction-exception */
	public function test_scheduled_upgrade_imports_registry_without_scheduling_disabled_sync() :void {
		$this->runWithSeededCronPreservationCheck(
			$this->requireController()->prefix( 'integration-test-upgrade-import-disabled-cron-owner' ),
			function () :void {
				$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
				$this->runWithImportExportSitesPersistentMutation( function () :void {
					$con = $this->requireController();
					$url = 'https://upgrade-import-disabled.example.com';
					$con->opts
						->optSet( 'importexport_enable', 'N' )
						->optSet( 'importexport_whitelist', [ $url ] )
						->optSet( 'import_url_ids', [
							\hash( 'md5', $url ) => 'upgrade-import-disabled-id',
						] )
						->store();
					$this->dropImportExportSitesTable();
					\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );

					$con->cfg->previous_version = '0.0.1';
					( new HandleUpgrade() )->execute();
					do_action( $con->prefix( 'plugin-upgrade' ), '0.0.1' );

					$row = $this->requireSite( $url );
					$this->assertSame( SitesDB::STATUS_ACTIVE, $row->status );
					$this->assertSame( 'upgrade-import-disabled-id', $row->import_id );
					$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
				}, true );
			}
		);
	}

	/** @group database-transaction-exception */
	public function test_config_rebuild_imports_legacy_settings_into_registry_without_upgrade_cron() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->runWithImportExportSitesPersistentMutation( function () :void {
			$con = $this->requireController();
			$url = 'https://config-rebuild-import.example.com';
			$con->opts
				->optSet( 'importexport_enable', 'Y' )
				->optSet( 'importexport_whitelist', [ $url ] )
				->optSet( 'import_url_ids', [
					\hash( 'md5', $url ) => 'config-rebuild-import-id',
				] )
				->store();
			$this->dropImportExportSitesTable();
			\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );

			$con->cfg->rebuilt = true;
			$this->runConfigRebuildImport();

			$row = $this->requireSite( $url );
			$this->assertSame( SitesDB::STATUS_ACTIVE, $row->status );
			$this->assertSame( 'config-rebuild-import-id', $row->import_id );
			$this->assertSame( [ $url ], $con->opts->optGet( 'importexport_whitelist' ) );
			$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
		} );
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	/** @group database-transaction-exception */
	public function test_config_rebuild_imports_registry_without_scheduling_disabled_sync() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->runWithImportExportSitesPersistentMutation( function () :void {
			$con = $this->requireController();
			$url = 'https://config-rebuild-disabled.example.com';
			$con->opts
				->optSet( 'importexport_enable', 'N' )
				->optSet( 'importexport_whitelist', [ $url ] )
				->optSet( 'import_url_ids', [
					\hash( 'md5', $url ) => 'config-rebuild-disabled-id',
				] )
				->store();
			$this->dropImportExportSitesTable();
			\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );

			$con->cfg->rebuilt = true;
			$this->runConfigRebuildImport();

			$row = $this->requireSite( $url );
			$this->assertSame( SitesDB::STATUS_ACTIVE, $row->status );
			$this->assertSame( 'config-rebuild-disabled-id', $row->import_id );
			$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
		} );
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	/** @group database-transaction-exception */
	public function test_same_version_config_signature_rebuild_imports_legacy_settings_into_registry() :void {
		$this->runWithImportExportSitesPersistentMutation( function () :void {
			$con = $this->requireController();
			$stored = $con->cfg->getRawData();
			$stored[ 'hash' ] = 'stale-signature';
			$stored[ 'properties' ][ 'version' ] = $con->cfg->properties[ 'version' ];
			Services::WpGeneral()->updateOption( $this->configStoreKey, $stored );

			$cfg = ( new LoadConfig( $con->paths->forPluginItem( 'plugin.json' ), $this->configStoreKey ) )->run();

			$this->assertTrue( $cfg->rebuilt );
			$this->assertSame( $con->cfg->properties[ 'version' ], $cfg->properties[ 'version' ] );

			$url = 'https://same-version-rebuild.example.com';
			$con->opts
				->optSet( 'importexport_whitelist', [ $url ] )
				->optSet( 'import_url_ids', [
					\hash( 'md5', $url ) => 'same-version-rebuild-id',
				] )
				->store();
			$this->dropImportExportSitesTable();

			$con->cfg->rebuilt = $cfg->rebuilt;
			$this->runConfigRebuildImport();

			$row = $this->requireSite( $url );
			$this->assertSame( SitesDB::STATUS_ACTIVE, $row->status );
			$this->assertSame( 'same-version-rebuild-id', $row->import_id );
		} );
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
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

	public function test_equivalent_url_spellings_share_one_registry_identity() :void {
		$repo = $this->repo();
		$first = $repo->upsertActive(
			'HTTPS://Canonical.Example.COM:443/Mixed/Path/?source=first#fragment',
			SitesDB::SOURCE_MANUAL
		);
		$second = $repo->upsertActive( 'https://canonical.example.com/Mixed/Path', SitesDB::SOURCE_EXPORT );

		$this->assertInstanceOf( Record::class, $first );
		$this->assertInstanceOf( Record::class, $second );
		$this->assertSame( $first->id, $second->id );
		$this->assertSame( 'https://canonical.example.com/Mixed/Path', $second->url );
		$this->assertSame(
			$repo->urlHash( 'HTTPS://CANONICAL.EXAMPLE.COM:443/Mixed/Path/' ),
			$repo->urlHash( 'https://canonical.example.com/Mixed/Path' )
		);
		$this->assertCount( 1, $repo->findByUrls( [
			'HTTPS://CANONICAL.EXAMPLE.COM:443/Mixed/Path/',
			'https://canonical.example.com/Mixed/Path',
		] ) );
	}

	public function test_queue_site_ids_coalesces_active_rows_and_ignores_deleted_or_missing_ids() :void {
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
		$queuedCount = $repo->queueSiteIds( \array_merge( $activeIds, [ $deleted->id, 9999999 ] ) );

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
	}

	public function test_repeated_queue_requests_coalesce_queued_rows_and_preserve_in_flight_rows() :void {
		$first = 1712620800;
		$second = 1712707200;
		$this->setRequestTimestamp( $first );
		$repo = $this->repo();
		$idle = $repo->upsertActive( 'https://queue-coalesce-idle.example.com', SitesDB::SOURCE_MANUAL, 'idle-id', true );
		$repo->recordExportSuccess( $idle, SitesDB::EXPORT_RESULT_SUCCESS, 'idle-id' );
		$idle = $repo->findById( $idle->id, true );

		$queued = $repo->upsertActive( 'https://queue-coalesce-queued.example.com', SitesDB::SOURCE_MANUAL, 'queued-id', true );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $queued->id, [
			'priority'             => 9,
			'queued_at'            => $first - 500,
			'next_ping_at'         => $second + 3600,
			'consecutive_failures' => 3,
		] );
		$queued = $repo->findById( $queued->id, true );
		$queuedDue = $repo->upsertActive( 'https://queue-coalesce-queued-due.example.com', SitesDB::SOURCE_MANUAL, '', true );

		$processing = $repo->upsertActive( 'https://queue-coalesce-processing.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $processing, $first ) );
		$processing = $repo->findById( $processing->id, true );

		$waiting = $repo->upsertActive( 'https://queue-coalesce-waiting.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $waiting, $first ) );
		$this->assertSame( 1, $repo->recordNotifyDispatched( $waiting, 204, $first + 600 ) );
		$waiting = $repo->findById( $waiting->id, true );

		$pendingInvite = $repo->upsertPendingClientSite( 'https://queue-coalesce-invite.example.com', SitesDB::SOURCE_MANUAL, true );
		$pendingConnection = $repo->upsertPendingClientSite( 'https://queue-coalesce-connection.example.com', SitesDB::SOURCE_MANUAL, false );
		$deleted = $repo->upsertActive( 'https://queue-coalesce-deleted.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->softDeleteUrl( $deleted->url );

		$preserved = [];
		$this->setRequestTimestamp( $second );
		$liveProcessing = $repo->upsertActive( 'https://queue-coalesce-live-processing.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $liveProcessing, $second ) );
		$liveProcessing = $repo->findById( $liveProcessing->id, true );
		$liveWaiting = $repo->upsertActive( 'https://queue-coalesce-live-waiting.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $liveWaiting, $second ) );
		$this->assertSame( 1, $repo->recordNotifyDispatched( $liveWaiting, 204, $second + 600 ) );
		$liveWaiting = $repo->findById( $liveWaiting->id, true );

		foreach ( [ $queued, $queuedDue, $processing, $waiting, $liveProcessing, $liveWaiting, $pendingInvite, $pendingConnection ] as $row ) {
			$preserved[ $row->id ] = $repo->findById( $row->id, true )->getRawData();
		}
		$ids = [ $idle->id, $queued->id, $queuedDue->id, $processing->id, $waiting->id, $liveProcessing->id, $liveWaiting->id, $pendingInvite->id, $pendingConnection->id, $deleted->id, 9999999 ];

		$this->assertSame( 3, $repo->queueSiteIds( $ids ) );
		$idleAfter = $repo->findById( $idle->id, true );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $idleAfter->queue_status );
		$this->assertSame( $second, $idleAfter->queued_at );
		$this->assertSame( $second, $idleAfter->next_ping_at );
		foreach ( [ $queued, $queuedDue ] as $row ) {
			$preserved[ $row->id ][ 'queued_at' ] = $second;
			$preserved[ $row->id ][ 'next_ping_at' ] = $second;
			$preserved[ $row->id ][ 'updated_at' ] = $second;
		}
		foreach ( $preserved as $id => $raw ) {
			$this->assertSame( $raw, $repo->findById( $id, true )->getRawData() );
		}

		$afterFirst = [];
		foreach ( [ $idle->id, $queued->id, $queuedDue->id, $processing->id, $waiting->id, $liveProcessing->id, $liveWaiting->id, $pendingInvite->id, $pendingConnection->id ] as $id ) {
			$afterFirst[ $id ] = $repo->findById( $id, true )->getRawData();
		}
		$this->setRequestTimestamp( $second + 100 );
		$this->assertSame( 3, $repo->queueAllActive() );
		foreach ( $afterFirst as $id => $raw ) {
			$this->assertSame( $raw, $repo->findById( $id, true )->getRawData() );
		}
	}

	#[DataProvider( 'queueMutationRaceProvider' )]
	public function test_queue_request_evaluates_state_at_update_time(
		string $case,
		string $selectedState,
		string $winningState,
		int $expectedCount,
		string $expectedFinalState
	) :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$row = $repo->upsertActive( "https://queue-race-{$case}.example.com", SitesDB::SOURCE_MANUAL, '', true );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
			'queue_status'       => $selectedState,
			'queued_at'          => $now - 500,
			'next_ping_at'       => $now + 500,
			'picked_at'          => $selectedState === SitesDB::QUEUE_PROCESSING ? $now - 10 : 0,
			'lock_until'         => $selectedState === SitesDB::QUEUE_PROCESSING ? $now + 50 : 0,
			'expected_export_by' => $selectedState === SitesDB::QUEUE_WAITING_EXPORT ? $now + 600 : 0,
		] );
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$interleaved = false;
		$winnerState = null;
		$updateState = \strpos( $case, 'retry-' ) === 0 ? SitesDB::QUEUE_QUEUED : SitesDB::QUEUE_IDLE;
		$filter = function ( string $query ) use ( $repo, $row, $table, $winningState, $updateState, $now, &$interleaved, &$winnerState ) :string {
			if ( !$interleaved
				 && \strpos( $query, "UPDATE `{$table}`" ) !== false
				 && \strpos( $query, "WHERE `id`={$row->id} AND" ) !== false
				 && \strpos( $query, "AND `queue_status`='{$updateState}'" ) !== false ) {
				$interleaved = true;
				$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
					'queue_status'       => $winningState,
					'queued_at'          => $now - 400,
					'next_ping_at'       => $now + 400,
					'picked_at'          => $winningState === SitesDB::QUEUE_PROCESSING ? $now - 5 : 0,
					'lock_until'         => $winningState === SitesDB::QUEUE_PROCESSING ? $now + 55 : 0,
					'expected_export_by' => $winningState === SitesDB::QUEUE_WAITING_EXPORT ? $now + 700 : 0,
				] );
				$winnerState = $repo->findById( $row->id, true )->getRawData();
			}
			return $query;
		};
		\add_filter( 'query', $filter, 1000 );
		try {
			$count = $repo->queueSiteIds( [ $row->id ] );
		}
		finally {
			\remove_filter( 'query', $filter, 1000 );
		}

		$this->assertTrue( $interleaved );
		$this->assertSame( $expectedCount, $count );
		$persisted = $repo->findById( $row->id, true );
		$this->assertSame( $expectedFinalState, $persisted->queue_status );
		if ( $winningState === SitesDB::QUEUE_QUEUED ) {
			$winnerState[ 'queued_at' ] = $now;
			$winnerState[ 'next_ping_at' ] = $now;
		}
		if ( $winningState !== SitesDB::QUEUE_IDLE ) {
			$this->assertSame( $winnerState, $persisted->getRawData() );
		}
	}

	public static function queueMutationRaceProvider() :array {
		return [
			'queued retry becomes processing' => [ 'retry-processing', SitesDB::QUEUE_QUEUED, SitesDB::QUEUE_PROCESSING, 0, SitesDB::QUEUE_PROCESSING ],
			'queued retry becomes waiting' => [ 'retry-waiting', SitesDB::QUEUE_QUEUED, SitesDB::QUEUE_WAITING_EXPORT, 0, SitesDB::QUEUE_WAITING_EXPORT ],
			'idle becomes processing' => [ 'idle-processing', SitesDB::QUEUE_IDLE, SitesDB::QUEUE_PROCESSING, 0, SitesDB::QUEUE_PROCESSING ],
			'idle becomes waiting' => [ 'idle-waiting', SitesDB::QUEUE_IDLE, SitesDB::QUEUE_WAITING_EXPORT, 0, SitesDB::QUEUE_WAITING_EXPORT ],
			'idle loses to another queue request' => [ 'idle-queued', SitesDB::QUEUE_IDLE, SitesDB::QUEUE_QUEUED, 1, SitesDB::QUEUE_QUEUED ],
			'processing becomes idle' => [ 'processing-idle', SitesDB::QUEUE_PROCESSING, SitesDB::QUEUE_IDLE, 1, SitesDB::QUEUE_QUEUED ],
			'waiting becomes idle' => [ 'waiting-idle', SitesDB::QUEUE_WAITING_EXPORT, SitesDB::QUEUE_IDLE, 1, SitesDB::QUEUE_QUEUED ],
			'queued becomes idle' => [ 'queued-idle', SitesDB::QUEUE_QUEUED, SitesDB::QUEUE_IDLE, 1, SitesDB::QUEUE_QUEUED ],
			'pending invite becomes idle' => [ 'invite-idle', SitesDB::QUEUE_PENDING_INVITE, SitesDB::QUEUE_IDLE, 1, SitesDB::QUEUE_QUEUED ],
			'pending connection becomes idle' => [ 'connection-idle', SitesDB::QUEUE_PENDING_CONNECTION, SitesDB::QUEUE_IDLE, 1, SitesDB::QUEUE_QUEUED ],
		];
	}

	public function test_manual_queue_brings_future_retry_forward_and_preserves_history() :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://queue-controller-existing.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
			'priority'             => 7,
			'queued_at'            => $now - 100,
			'next_ping_at'         => $now + 3600,
			'consecutive_failures' => 2,
			'last_export_failure_at' => $now,
			'last_export_result_code' => SitesDB::EXPORT_RESULT_TIMEOUT,
			'last_export_error' => 'export_not_requested_before_grace_window',
		] );
		$before = $repo->findById( $row->id, true )->getRawData();
		$problem = $this->retrieveImportExportSitesTableData( 'queue-controller-existing', [
			'sync_state' => [ SiteSyncStatusBuilder::STATE_PROBLEM ],
		] );
		$this->assertSame( [ $row->id ], \array_column( $problem[ 'data' ], 'rid' ) );

		$this->assertSame( 1, $repo->queueAllActive() );
		$this->assertSame( $before, $repo->findById( $row->id, true )->getRawData() );
		$unselected = $repo->upsertActive( 'https://queue-unselected.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $unselected->id, [
			'next_ping_at' => $now + 3600,
		] );
		$unselectedBefore = $repo->findById( $unselected->id, true )->getRawData();

		$this->assertSame( 1, ( new ImportExportController() )->queueSitesForSync( [ $row->id ] ) );

		$before[ 'queued_at' ] = $now;
		$before[ 'next_ping_at' ] = $now;
		$this->assertSame( $before, $repo->findById( $row->id, true )->getRawData() );
		$this->assertSame( $unselectedBefore, $repo->findById( $unselected->id, true )->getRawData() );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
		$pending = $this->retrieveImportExportSitesTableData( 'queue-controller-existing', [
			'sync_state' => [ SiteSyncStatusBuilder::STATE_PENDING ],
		] );
		$this->assertSame( [ $row->id ], \array_column( $pending[ 'data' ], 'rid' ) );
		$this->assertSame( SiteSyncStatusBuilder::STATE_PENDING, $pending[ 'data' ][ 0 ][ 'sync_state' ] );
		$problem = $this->retrieveImportExportSitesTableData( 'queue-controller-existing', [
			'sync_state' => [ SiteSyncStatusBuilder::STATE_PROBLEM ],
		] );
		$this->assertSame( 0, (int)$problem[ 'recordsFiltered' ] );
	}

	public function test_queue_request_does_not_count_failed_idle_update() :void {
		global $wpdb;
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://queue-write-failure.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordExportSuccess( $row, SitesDB::EXPORT_RESULT_SUCCESS );
		$before = $repo->findById( $row->id, true )->getRawData();
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$failed = false;
		$filter = static function ( string $query ) use ( $row, $table, &$failed ) :string {
			if ( !$failed
				 && \strpos( $query, "UPDATE `{$table}`" ) !== false
				 && \strpos( $query, "WHERE `id`={$row->id} AND" ) !== false
				 && \strpos( $query, "`queue_status`='idle'" ) !== false ) {
				$failed = true;
				return 'UPDATE intentionally_invalid_queue_sql';
			}
			return $query;
		};
		$previousSuppressErrors = $wpdb->suppress_errors( true );
		\add_filter( 'query', $filter, 1000 );
		try {
			$count = $repo->queueSiteIds( [ $row->id ] );
		}
		finally {
			\remove_filter( 'query', $filter, 1000 );
			$wpdb->suppress_errors( $previousSuppressErrors );
		}

		$this->assertTrue( $failed );
		$this->assertSame( 0, $count );
		$this->assertSame( $before, $repo->findById( $row->id, true )->getRawData() );
	}

	public function test_export_success_retries_one_transient_database_failure() :void {
		global $wpdb;
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://export-success-retry.example.com', SitesDB::SOURCE_MANUAL, 'retry-id', true );
		$this->assertTrue( $repo->startNotificationAttempt( $row, $now ) );
		$started = $repo->findById( $row->id, true );
		$this->assertSame( 1, $repo->recordNotifyDispatched( $started, 204, $now + QueueProcessor::EXPORT_GRACE ) );
		$waiting = $repo->findById( $row->id, true );
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$attempts = 0;
		$filter = function ( string $query ) use ( $table, &$attempts ) :string {
			if ( $this->isExportSuccessUpdate( $query, $table ) ) {
				$attempts++;
				if ( $attempts === 1 ) {
					return 'UPDATE intentionally_invalid_export_success_sql';
				}
			}
			return $query;
		};
		$previousSuppressErrors = $wpdb->suppress_errors( true );
		\add_filter( 'query', $filter, 1000 );
		try {
			$result = $repo->recordExportSuccess( $waiting, SitesDB::EXPORT_RESULT_SUCCESS, 'retry-id' );
		}
		finally {
			\remove_filter( 'query', $filter, 1000 );
			$wpdb->suppress_errors( $previousSuppressErrors );
		}

		$this->assertSame( 2, $attempts );
		$this->assertSame( 1, $result );
		$persisted = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_IDLE, $persisted->queue_status );
		$this->assertSame( $now, $persisted->last_export_success_at );
		$this->assertSame( SitesDB::EXPORT_RESULT_SUCCESS, $persisted->last_export_result_code );

		$this->setRequestTimestamp( $waiting->expected_export_by );
		$this->assertSame( [], $repo->selectExportMaintenanceRows( 5 ) );
		$sender = new ImportExportPingSenderTestDouble( true, 204, '' );
		( new ImportExportQueueProcessorTestDouble( $sender, null, $repo ) )->runFromCron();
		$this->assertSame( [], $sender->urls );
		$afterGrace = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_IDLE, $afterGrace->queue_status );
		$this->assertSame( SitesDB::EXPORT_RESULT_SUCCESS, $afterGrace->last_export_result_code );
		$this->assertSame( 0, $afterGrace->consecutive_failures );
	}

	public function test_export_request_marker_uses_supplied_active_row_without_lookup() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://export-request-marker.example.com', SitesDB::SOURCE_MANUAL );
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$updates = 0;
		$selects = 0;
		$filter = static function ( string $query ) use ( $table, &$updates, &$selects ) :string {
			if ( \strpos( $query, "UPDATE `{$table}` SET `last_export_request_at`=" ) === 0 ) {
				$updates++;
			}
			elseif ( \strpos( $query, "SELECT * FROM `{$table}`" ) === 0 ) {
				$selects++;
			}
			return $query;
		};

		$this->setRequestTimestamp( 1712620800 );
		\add_filter( 'query', $filter, 1000 );
		try {
			$repo->recordExportRequested( $row );
		}
		finally {
			\remove_filter( 'query', $filter, 1000 );
		}

		$this->assertSame( 1, $updates );
		$this->assertSame( 0, $selects );
		$persisted = $repo->findById( $row->id, true );
		$this->assertSame( 1712620800, $persisted->last_export_request_at );

		$repo->softDeleteUrl( $row->url );
		$this->setRequestTimestamp( 1712620900 );
		$repo->recordExportRequested( $row );
		$this->assertSame( 1712620800, $repo->findById( $row->id, true )->last_export_request_at );
	}

	public function test_export_success_persistent_database_failure_keeps_timeout_recovery() :void {
		global $wpdb;
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://export-success-persistent-failure.example.com', SitesDB::SOURCE_MANUAL, 'persistent-id', true );
		$this->assertTrue( $repo->startNotificationAttempt( $row, $now ) );
		$started = $repo->findById( $row->id, true );
		$deadline = $now + QueueProcessor::EXPORT_GRACE;
		$this->assertSame( 1, $repo->recordNotifyDispatched( $started, 204, $deadline ) );
		$waiting = $repo->findById( $row->id, true );
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$attempts = 0;
		$filter = function ( string $query ) use ( $table, &$attempts ) :string {
			if ( $this->isExportSuccessUpdate( $query, $table ) ) {
				$attempts++;
				return 'UPDATE intentionally_invalid_export_success_sql';
			}
			return $query;
		};
		$previousSuppressErrors = $wpdb->suppress_errors( true );
		\add_filter( 'query', $filter, 1000 );
		try {
			$result = $repo->recordExportSuccess( $waiting, SitesDB::EXPORT_RESULT_SUCCESS, 'persistent-id' );
		}
		finally {
			\remove_filter( 'query', $filter, 1000 );
			$wpdb->suppress_errors( $previousSuppressErrors );
		}

		$this->assertFalse( $result );
		$this->assertSame( 2, $attempts );
		$persisted = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_WAITING_EXPORT, $persisted->queue_status );
		$this->assertSame( 0, $persisted->last_export_success_at );

		$this->setRequestTimestamp( $deadline );
		$maintenance = $repo->selectExportMaintenanceRows( 5 );
		$this->assertSame( [ $row->id ], \array_map( static fn( Record $item ) :int => $item->id, $maintenance ) );
		$this->assertSame( 1, $repo->recordExportTimeout( $maintenance[ 0 ] ) );
		$timedOut = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $timedOut->queue_status );
		$this->assertSame( SitesDB::EXPORT_RESULT_TIMEOUT, $timedOut->last_export_result_code );
		$this->assertSame( 1, $timedOut->consecutive_failures );
		$this->assertSame( $deadline + 15*\MINUTE_IN_SECONDS, $timedOut->next_ping_at );
		$this->assertNull( $repo->selectNextDueWork( $timedOut->next_ping_at - 1 ) );
	}

	public function test_export_success_retry_does_not_overwrite_newer_queue_operations() :void {
		global $wpdb;
		$base = 1712620800;
		foreach ( [ 'manual-retry', 'replacement-wait', 'case-only-import-id' ] as $offset => $case ) {
			$now = $base + $offset*7200;
			$this->setRequestTimestamp( $now );
			$repo = $this->repo();
			$row = $repo->upsertActive( "https://export-success-stale-{$case}.example.com", SitesDB::SOURCE_MANUAL, 'original-id', true );
			$this->assertTrue( $repo->startNotificationAttempt( $row, $now ) );
			$started = $repo->findById( $row->id, true );
			$this->assertSame( 1, $repo->recordNotifyDispatched( $started, 204, $now + QueueProcessor::EXPORT_GRACE ) );
			$waiting = $repo->findById( $row->id, true );
			$table = $this->requireController()->db_con->import_export_sites->getTable();
			$attempts = 0;
			$winner = null;
			$interleaving = false;
			$filter = function ( string $query ) use ( $case, $now, $repo, $waiting, $table, &$attempts, &$winner, &$interleaving ) :string {
				if ( $interleaving || !$this->isExportSuccessUpdate( $query, $table ) ) {
					return $query;
				}
				$attempts++;
				if ( $attempts === 1 ) {
					return 'UPDATE intentionally_invalid_export_success_sql';
				}
				$interleaving = true;
				if ( $case === 'manual-retry' ) {
					$this->setRequestTimestamp( $waiting->expected_export_by );
					$this->assertSame( 1, $repo->recordExportTimeout( $waiting ) );
					$this->assertSame( 1, $repo->queueSiteIds( [ $waiting->id ] ) );
				}
				elseif ( $case === 'replacement-wait' ) {
					$this->setRequestTimestamp( $now + 60 );
					$replacement = $repo->upsertActive( $waiting->url, SitesDB::SOURCE_EXPORT, 'replacement-id', true );
					$this->assertInstanceOf( Record::class, $replacement );
					$this->assertTrue( $repo->startNotificationAttempt( $replacement, $now + 60 ) );
					$replacementStarted = $repo->findById( $waiting->id, true );
					$this->assertSame( 1, $repo->recordNotifyDispatched( $replacementStarted, 204, $now + 660 ) );
				}
				else {
					$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $waiting->id, [
						'import_id' => 'ORIGINAL-ID',
					] );
				}
				$winner = $repo->findById( $waiting->id, true )->getRawData();
				$interleaving = false;
				return $query;
			};
			$previousSuppressErrors = $wpdb->suppress_errors( true );
			\add_filter( 'query', $filter, 1000 );
			try {
				$result = $repo->recordExportSuccess( $waiting, SitesDB::EXPORT_RESULT_SUCCESS, 'original-id' );
			}
			finally {
				\remove_filter( 'query', $filter, 1000 );
				$wpdb->suppress_errors( $previousSuppressErrors );
			}

			$this->assertSame( 2, $attempts, $case );
			$this->assertSame( 0, $result, $case );
			$this->assertIsArray( $winner, $case );
			$this->assertSame( $winner, $repo->findById( $waiting->id, true )->getRawData(), $case );
		}
	}

	public function test_due_work_selection_returns_one_row_without_claiming_it() :void {
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
		$selected = $repo->selectNextDueWork();

		$this->assertNotNull( $selected );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $selected->queue_status );
		$this->assertSame( 0, $selected->picked_at );
		$this->assertSame( 0, $selected->lock_until );
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

	public function test_queue_processor_handles_more_than_five_fast_sites_and_keeps_sync_success_separate_from_ping() :void {
		$repo = $this->repo();
		for ( $i = 1; $i <= 12; $i++ ) {
			$repo->upsertActive( sprintf( 'https://slave-%02d.example.com', $i ), SitesDB::SOURCE_MANUAL, '', true );
		}

		( new ImportExportQueueProcessorTestDouble( new ImportExportPingSenderTestDouble( true, 204, '' ) ) )->runFromCron();

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

		$this->assertSame( 12, $waiting );
		$this->assertSame( 0, $stillDue );
	}

	public function test_queue_processor_uses_exact_start_cutoff_and_full_notification_timeout() :void {
		$clock = (object)[ 'now' => 100.0 ];
		$repo = new ImportExportSelectionClockRepositoryTestDouble( $clock );
		$first = $repo->upsertActive( 'https://cutoff-first.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$second = $repo->upsertActive( 'https://cutoff-second.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->advanceAfterSelectionTo = 109.5;
		$sender = new ImportExportPingSenderTestDouble(
			true,
			204,
			'',
			static function () use ( $clock ) :void {
				$clock->now = 110.0;
			}
		);
		$processor = new ImportExportQueueProcessorTestDouble(
			$sender,
			null,
			$repo,
			static fn() :float => $clock->now
		);

		$processor->runFromCron();

		$this->assertSame( [ 5 ], $sender->timeouts );
		$this->assertSame( SitesDB::QUEUE_WAITING_EXPORT, $repo->findById( $first->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $second->id, true )->queue_status );
		$this->assertSame( 1, $processor->dispatches );

		$third = $repo->upsertActive( 'https://cutoff-exact.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$clock->now = 200.0;
		$repo->advanceAfterSelectionTo = 210.0;
		$exactSender = new ImportExportPingSenderTestDouble( true, 204, '' );
		$exactProcessor = new ImportExportQueueProcessorTestDouble(
			$exactSender,
			null,
			$repo,
			static fn() :float => $clock->now
		);
		$exactProcessor->runFromCron();

		$this->assertSame( [], $exactSender->timeouts );
		$selectedAtCutoff = $repo->findById( $second->id, true );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $selectedAtCutoff->queue_status );
		$this->assertSame( 0, $selectedAtCutoff->picked_at );
		$this->assertSame( 0, $selectedAtCutoff->lock_until );
		$this->assertSame( 0, ( new NotificationMetadata() )->attemptsStarted( $selectedAtCutoff->meta ) );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $third->id, true )->queue_status );
		$this->assertSame( 0, $exactProcessor->dispatches );
	}

	public function test_zero_progress_memory_stop_leaves_due_work_for_scheduler() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://memory-stop.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$sender = new ImportExportPingSenderTestDouble( true, 204, '' );
		$processor = new ImportExportQueueProcessorTestDouble( $sender, null, $repo );
		$processor->memoryExceeded = true;

		$processor->runFromCron();

		$this->assertSame( [], $sender->urls );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $row->id, true )->queue_status );
		$this->assertSame( 0, $processor->dispatches );
	}

	public function test_queue_processor_does_not_spin_when_nothing_is_runnable() :void {
		$repo = $this->repo();
		$sender = new ImportExportPingSenderTestDouble( true, 204, '' );
		$emptyProcessor = new ImportExportQueueProcessorTestDouble( $sender, null, $repo );

		$emptyProcessor->runFromCron();

		$this->assertSame( [], $sender->urls );
		$this->assertSame( 0, $emptyProcessor->dispatches );

		$now = Services::Request()->ts();
		$futureRetry = $repo->upsertActive( 'https://future-retry.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $futureRetry->id, [
			'next_ping_at' => $now + 60,
		] );
		$processing = $repo->upsertActive( 'https://unexpired-processing.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $processing, $now ) );
		$waiting = $repo->upsertActive( 'https://unexpired-waiting.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $waiting, $now ) );
		$waiting = $repo->findById( $waiting->id, true );
		$this->assertSame( 1, $repo->recordNotifyDispatched( $waiting, 204, $now + 600 ) );
		$passive = $repo->upsertPendingClientSite( 'https://passive-pending.example.com', SitesDB::SOURCE_MANUAL, false );
		$before = [];
		foreach ( [ $futureRetry, $processing, $waiting, $passive ] as $row ) {
			$before[ $row->id ] = $repo->findById( $row->id, true )->getRawData();
		}

		$processor = new ImportExportQueueProcessorTestDouble( $sender, null, $repo );
		$processor->runFromCron();

		$this->assertSame( [], $sender->urls );
		$this->assertSame( 0, $processor->dispatches );
		$this->assertFalse( $repo->hasActionableWork() );
		foreach ( $before as $id => $raw ) {
			$this->assertSame( $raw, $repo->findById( $id, true )->getRawData() );
		}
	}

	public function test_maintenance_reaching_cutoff_hands_due_work_to_one_successor() :void {
		$clock = (object)[ 'now' => 100.0 ];
		$repo = new ImportExportMaintenanceClockRepositoryTestDouble( $clock );
		$expired = $repo->upsertActive( 'https://maintenance-expired.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $expired, Services::Request()->ts() ) );
		$expired = $repo->findById( $expired->id, true );
		$this->assertSame( 1, $repo->recordNotifyDispatched( $expired, 204, Services::Request()->ts() - 1 ) );
		$due = $repo->upsertActive( 'https://maintenance-due.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$sender = new ImportExportPingSenderTestDouble( true, 204, '' );
		$processor = new ImportExportQueueProcessorTestDouble( $sender, null, $repo, static fn() :float => $clock->now );

		$processor->runFromCron();

		$this->assertSame( [], $sender->urls );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $due->id, true )->queue_status );
		$this->assertSame( 1, $processor->dispatches );
	}

	#[DataProvider( 'provideNonExecutingDispatchResults' )]
	public function test_scheduler_recovers_when_successor_dispatch_does_not_execute( string $dispatchResult ) :void {
		$repo = $this->repo();
		$first = $repo->upsertActive( 'https://dispatch-first.example.com', SitesDB::SOURCE_MANUAL, 'first', true );
		$second = $repo->upsertActive( 'https://dispatch-second.example.com', SitesDB::SOURCE_MANUAL, 'second', true );
		$clock = (object)[ 'now' => 100.0 ];
		$sender = new ImportExportPingSenderTestDouble(
			true,
			204,
			'',
			static function () use ( $clock ) :void {
				$clock->now += 10.1;
			}
		);
		$firstProcessor = new ImportExportQueueProcessorTestDouble( $sender, null, $repo, static fn() :float => $clock->now );
		$firstProcessor->dispatchResult = $dispatchResult === 'rejected'
			? new \WP_Error( 'dispatch_rejected' )
			: [ 'response' => [ 'code' => 202 ] ];
		$recoveryProcessor = new ImportExportQueueProcessorTestDouble( $sender, null, $repo, static fn() :float => $clock->now );
		$scheduler = new QueueScheduler( static fn() :bool => true, fn() => $recoveryProcessor->runFromCron() );
		$scheduler->setup();

		$firstProcessor->runFromCron();

		$this->assertSame( 1, $firstProcessor->dispatches );
		$this->assertSame( [ $first->url ], $sender->urls );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $second->id, true )->queue_status );
		$this->assertNotFalse( \wp_next_scheduled( $scheduler->hook() ) );

		\do_action( $scheduler->hook() );

		$this->assertSame( [ $first->url, $second->url ], $sender->urls );
		$this->assertSame( SitesDB::QUEUE_WAITING_EXPORT, $repo->findById( $second->id, true )->queue_status );
		$this->assertNotFalse( \wp_next_scheduled( $scheduler->hook() ) );
	}

	public static function provideNonExecutingDispatchResults() :array {
		return [
			'rejected'                 => [ 'rejected' ],
			'accepted but not executed' => [ 'accepted' ],
		];
	}

	public function test_three_interrupted_notification_starts_back_off_without_attempt_four_and_next_site_progresses() :void {
		$start = 1712620800;
		$this->setRequestTimestamp( $start );
		$repo = new ImportExportFailedNotificationWriteRepositoryTestDouble();
		$interrupted = $repo->upsertActive( 'https://interrupted-three.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->failedWrite = 'recordNotifyDispatched';
		$repo->failedRecordID = $interrupted->id;
		$sender = new ImportExportPingSenderTestDouble( true, 204, '' );

		foreach ( [ 1 => $start, 2 => $start + 60, 3 => $start + 120 ] as $attempt => $attemptAt ) {
			$this->setRequestTimestamp( $attemptAt );
			$processor = new ImportExportQueueProcessorTestDouble( $sender, null, $repo );
			$processor->runFromCron();

			$current = $repo->findById( $interrupted->id, true );
			$this->assertSame( SitesDB::QUEUE_PROCESSING, $current->queue_status );
			$this->assertSame( $attempt, ( new NotificationMetadata() )->attemptsStarted( $current->meta ) );
			$this->assertSame( $attemptAt, $current->picked_at );
			$this->assertSame( $attemptAt + 60, $current->lock_until );
			$this->assertSame( $attempt, \count( $sender->urls ) );
			$this->assertSame( 0, $processor->dispatches );

			if ( $attempt === 1 ) {
				$this->setRequestTimestamp( $start + 59 );
				$beforeExpiry = new ImportExportQueueProcessorTestDouble( $sender, null, $repo );
				$beforeExpiry->runFromCron();
				$this->assertCount( 1, $sender->urls );
				$this->assertSame( 0, $beforeExpiry->dispatches );
				$this->assertSame( $current->getRawData(), $repo->findById( $interrupted->id, true )->getRawData() );
			}
		}

		$later = $repo->upsertActive( 'https://after-interrupted.example.com', SitesDB::SOURCE_MANUAL, 'later', true );
		$repo->failedWrite = '';
		$this->setRequestTimestamp( $start + 180 );
		( new ImportExportQueueProcessorTestDouble( $sender, null, $repo ) )->runFromCron();

		$interrupted = $repo->findById( $interrupted->id, true );
		$this->assertSame( [ $interrupted->url, $interrupted->url, $interrupted->url, $later->url ], $sender->urls );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $interrupted->queue_status );
		$this->assertSame( 1, $interrupted->consecutive_failures );
		$this->assertSame( $start + 180 + 15*\MINUTE_IN_SECONDS, $interrupted->next_ping_at );
		$this->assertSame( 0, $interrupted->picked_at );
		$this->assertSame( 0, $interrupted->lock_until );
		$this->assertSame( 0, ( new NotificationMetadata() )->attemptsStarted( $interrupted->meta ) );
		$this->assertSame( 'Notification interrupted three times; retry deferred.', $interrupted->last_ping_error );
		$this->assertSame( SitesDB::QUEUE_WAITING_EXPORT, $repo->findById( $later->id, true )->queue_status );

		$this->setRequestTimestamp( $interrupted->next_ping_at - 1 );
		( new ImportExportQueueProcessorTestDouble( $sender, null, $repo ) )->runFromCron();
		$this->assertCount( 4, $sender->urls );

		$counterAtSend = [];
		$dueSender = new ImportExportPingSenderTestDouble(
			true,
			204,
			'',
			function ( int $count, string $url ) use ( $repo, &$counterAtSend ) :void {
				$counterAtSend[] = ( new NotificationMetadata() )->attemptsStarted( $repo->findByUrl( $url, true )->meta );
			}
		);
		$this->setRequestTimestamp( $interrupted->next_ping_at );
		( new ImportExportQueueProcessorTestDouble( $dueSender, null, $repo ) )->runFromCron();
		$interrupted = $repo->findById( $interrupted->id, true );
		$this->assertSame( [ $interrupted->url ], $dueSender->urls );
		$this->assertSame( [ 1 ], $counterAtSend );
		$this->assertSame( SitesDB::QUEUE_WAITING_EXPORT, $interrupted->queue_status );
		$this->assertSame( 1, $interrupted->consecutive_failures );
		$this->assertSame( 0, ( new NotificationMetadata() )->attemptsStarted( $interrupted->meta ) );
	}

	#[DataProvider( 'provideInterruptedNotificationBackoff' )]
	public function test_interrupted_notification_exhaustion_uses_existing_bounded_backoff(
		int $priorFailures,
		int $expectedDelay
	) :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://interrupted-backoff-'.$priorFailures.'.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$dbh = $this->requireController()->db_con->import_export_sites;
		$dbh->getQueryUpdater()->updateById( $row->id, [ 'consecutive_failures' => $priorFailures ] );
		$row = $repo->findById( $row->id, true );
		$this->assertTrue( $repo->startNotificationAttempt( $row, $now - 180 ) );
		$this->assertTrue( $repo->startNotificationAttempt( $repo->findById( $row->id, true ), $now - 120, true ) );
		$this->assertTrue( $repo->startNotificationAttempt( $repo->findById( $row->id, true ), $now - 60, true ) );

		$sender = new ImportExportPingSenderTestDouble( true, 204, '' );
		( new ImportExportQueueProcessorTestDouble( $sender, null, $repo ) )->runFromCron();

		$row = $repo->findById( $row->id, true );
		$this->assertSame( [], $sender->urls );
		$this->assertSame( $priorFailures + 1, $row->consecutive_failures );
		$this->assertSame( $now + $expectedDelay, $row->next_ping_at );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $row->queue_status );
		$this->assertSame( 0, ( new NotificationMetadata() )->attemptsStarted( $row->meta ) );
	}

	public static function provideInterruptedNotificationBackoff() :array {
		return [
			'15 minutes' => [ 0, 900 ],
			'30 minutes' => [ 1, 1800 ],
			'60 minutes' => [ 2, 3600 ],
			'one-day cap' => [ 7, 86400 ],
		];
	}

	public function test_legacy_expired_processing_row_without_notification_metadata_starts_at_counter_one() :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://legacy-processing.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
			'queue_status' => SitesDB::QUEUE_PROCESSING,
			'picked_at'    => $now - 120,
			'lock_until'   => $now - 1,
			'meta'         => $this->requireController()->db_con->import_export_sites->getRecord()->arrayDataWrap( [
				'preserved' => true,
			] ) ?? '',
		] );
		$counterAtSend = [];
		$sender = new ImportExportPingSenderTestDouble(
			true,
			204,
			'',
			function ( int $count, string $url ) use ( $repo, &$counterAtSend ) :void {
				$counterAtSend[] = ( new NotificationMetadata() )->attemptsStarted( $repo->findByUrl( $url, true )->meta );
			}
		);

		( new ImportExportQueueProcessorTestDouble( $sender, null, $repo ) )->runFromCron();

		$row = $repo->findById( $row->id, true );
		$this->assertSame( [ 1 ], $counterAtSend );
		$this->assertSame( SitesDB::QUEUE_WAITING_EXPORT, $row->queue_status );
		$this->assertSame( 0, ( new NotificationMetadata() )->attemptsStarted( $row->meta ) );
		$this->assertTrue( $row->meta[ 'preserved' ] );
	}

	public function test_notification_attempt_counter_tracks_recovery_and_resets_on_terminal_writes() :void {
		$repo = $this->repo();
		$success = $repo->upsertActive( 'https://notification-counter-success.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $success, Services::Request()->ts() - 61 ) );
		$success = $repo->findById( $success->id, true );
		$this->assertSame( 1, ( new NotificationMetadata() )->attemptsStarted( $success->meta ) );
		$this->assertTrue( $repo->startNotificationAttempt( $success, Services::Request()->ts(), true ) );
		$success = $repo->findById( $success->id, true );
		$this->assertSame( 2, ( new NotificationMetadata() )->attemptsStarted( $success->meta ) );
		$this->assertSame( 1, $repo->recordNotifyDispatched( $success, 204, Services::Request()->ts() + 600 ) );
		$this->assertSame( 0, ( new NotificationMetadata() )->attemptsStarted( $repo->findById( $success->id, true )->meta ) );

		$failure = $repo->upsertActive( 'https://notification-counter-failure.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $failure, Services::Request()->ts() ) );
		$failure = $repo->findById( $failure->id, true );
		$this->assertSame( 1, $repo->recordPingFailure( $failure, 503, 'service unavailable' ) );
		$this->assertSame( 0, ( new NotificationMetadata() )->attemptsStarted( $repo->findById( $failure->id, true )->meta ) );

		$exception = $repo->upsertActive( 'https://notification-counter-exception.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$sender = new ImportExportPingSenderTestDouble(
			true,
			204,
			'',
			null,
			static function () :array {
				throw new \RuntimeException( 'sender detail must not escape' );
			}
		);
		( new ImportExportQueueProcessorTestDouble( $sender ) )->runFromCron();
		$exception = $repo->findById( $exception->id, true );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $exception->queue_status );
		$this->assertSame( 'Notification sender failed.', $exception->last_ping_error );
		$this->assertSame( 0, ( new NotificationMetadata() )->attemptsStarted( $exception->meta ) );
	}

	#[DataProvider( 'provideFailedNotificationWrites' )]
	public function test_failed_notification_write_stops_worker_without_redispatch( string $failedWrite ) :void {
		$repo = new ImportExportFailedNotificationWriteRepositoryTestDouble();
		$start = Services::Request()->ts();
		$this->setRequestTimestamp( $start );
		$prior = $failedWrite === 'recordNotifyDispatched'
			? $repo->upsertActive( 'https://notification-write-prior.example.com', SitesDB::SOURCE_MANUAL, '', true )
			: null;
		$row = $repo->upsertActive( 'https://notification-write-'.$failedWrite.'.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$later = $repo->upsertActive( 'https://notification-write-later.example.com', SitesDB::SOURCE_MANUAL, '', true );
		if ( $failedWrite === 'recordInterruptedNotificationExhaustion' ) {
			$this->assertTrue( $repo->startNotificationAttempt( $row, $start - 180 ) );
			foreach ( [ $start - 120, $start - 60 ] as $attemptAt ) {
				$this->assertTrue( $repo->startNotificationAttempt( $repo->findById( $row->id, true ), $attemptAt, true ) );
			}
		}
		elseif ( $failedWrite === 'recordExportTimeout' ) {
			$this->assertTrue( $repo->startNotificationAttempt( $row, $start ) );
			$row = $repo->findById( $row->id, true );
			$this->assertSame( 1, $repo->recordNotifyDispatched( $row, 204, $start - 1 ) );
		}
		elseif ( $failedWrite === 'recordExportReconciliation' ) {
			$row = $this->setWaitingExportState( $row, [
				'expected_export_by' => $start + 600,
				'last_ping_success_at' => $start,
				'last_export_success_at' => $start,
			] );
		}
		$priorObservation = null;
		if ( $failedWrite === 'recordNotifyDispatched' ) {
			$priorObservation = SyncObservation::create( $start - 1, SyncObservation::PHASE_NOTIFICATION,
				SyncObservation::RESULT_NO_HTTP_RESPONSE, SyncObservation::VERIFICATION_NOT_APPLICABLE );
			$this->assertTrue( $repo->saveObservation( $row, SyncObservation::PHASE_NOTIFICATION, $priorObservation ) );
			$row = $repo->findById( $row->id, true );
		}
		$targetBefore = $repo->findById( $row->id, true )->getRawData();

		$repo->failedWrite = $failedWrite;
		$repo->failedRecordID = $row->id;
		$sender = new ImportExportPingSenderTestDouble( true, 204, '', null, static fn() :array => [
			'success'     => true,
			'http_code'   => 204,
			'error'       => '',
			'observation' => SyncObservation::create( $start, SyncObservation::PHASE_NOTIFICATION,
				SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED, SyncObservation::VERIFICATION_NOT_APPLICABLE,
				[ 'http_status' => 204 ] ),
		] );
		$processor = new ImportExportQueueProcessorTestDouble( $sender, null, $repo );
		$processor->runFromCron();

		$this->assertSame( 0, $processor->dispatches );
		$this->assertCount( $failedWrite === 'recordNotifyDispatched' ? 2 : 0, $sender->urls );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $later->id, true )->queue_status );
		if ( $prior instanceof Record ) {
			$this->assertSame( SitesDB::QUEUE_WAITING_EXPORT, $repo->findById( $prior->id, true )->queue_status );
		}
		$target = $repo->findById( $row->id, true );
		if ( $failedWrite === 'recordNotifyDispatched' ) {
			$this->assertSame( SitesDB::QUEUE_PROCESSING, $target->queue_status );
			$this->assertSame( $start, $target->picked_at );
			$this->assertSame( $start + 60, $target->lock_until );
			$this->assertSame( 1, ( new NotificationMetadata() )->attemptsStarted( $target->meta ) );
			$this->assertSame( $priorObservation, $repo->readObservation( $target, SyncObservation::PHASE_NOTIFICATION ) );
		}
		else {
			$this->assertSame( $targetBefore, $target->getRawData() );
		}
	}

	public static function provideFailedNotificationWrites() :array {
		return [
			'start' => [ 'startNotificationAttempt' ],
			'result' => [ 'recordNotifyDispatched' ],
			'exhaustion' => [ 'recordInterruptedNotificationExhaustion' ],
			'maintenance' => [ 'recordExportTimeout' ],
			'reconciliation' => [ 'recordExportReconciliation' ],
		];
	}

	public function test_recovery_rereads_persisted_state_and_skips_completed_row() :void {
		$repo = new ImportExportCompletedAfterRecoverySelectionRepositoryTestDouble();
		$stale = $repo->upsertActive( 'https://completed-before-recovery.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$later = $repo->upsertActive( 'https://progress-after-completed.example.com', SitesDB::SOURCE_MANUAL, 'later', true );
		$this->assertTrue( $repo->startNotificationAttempt( $stale, Services::Request()->ts() - 61 ) );
		$repo->completeAfterSelectingID = $stale->id;
		$sender = new ImportExportPingSenderTestDouble( true, 204, '' );

		( new ImportExportQueueProcessorTestDouble( $sender, null, $repo ) )->runFromCron();

		$this->assertSame( [ 'later' ], $sender->importIDs );
		$this->assertSame( SitesDB::QUEUE_IDLE, $repo->findById( $stale->id, true )->queue_status );
		$this->assertSame( 0, $repo->findById( $stale->id, true )->consecutive_failures );
		$this->assertSame( SitesDB::QUEUE_WAITING_EXPORT, $repo->findById( $later->id, true )->queue_status );
	}

	public function test_three_hundred_mixed_rows_drain_in_global_order_across_bounded_workers() :void {
		$repo = $this->repo();
		$failedURL = 'https://mixed-notify-failing.example.com';
		$slowURL = 'https://mixed-notify-slow.example.com';
		$rows = [];
		$rankedRows = [];
		$now = Services::Request()->ts();
		for ( $i = 0; $i < 300; $i++ ) {
			if ( $i%50 === 0 ) {
				$url = sprintf( 'https://mixed-invite-%03d.example.com', $i );
				$row = $repo->upsertPendingClientSite( $url, SitesDB::SOURCE_MANUAL, true );
			}
			else {
				$url = $i === 101 ? $slowURL : ( $i === 201 ? $failedURL : sprintf( 'https://mixed-notify-%03d.example.com', $i ) );
				$row = $repo->upsertActive( $url, SitesDB::SOURCE_MANUAL, 'import-'.$i, true );
			}
			$pair = \intdiv( $i, 2 );
			$priority = $pair%4;
			$dueAt = $now - $pair%17;
			$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
				'priority'     => $priority,
				'next_ping_at' => $dueAt,
			] );
			$row = $repo->findById( $row->id, true );
			$this->assertSame( $priority, $row->priority );
			$this->assertSame( $dueAt, $row->next_ping_at );
			$rows[] = $row;
			$rankedRows[] = [
				'id'       => $row->id,
				'url'      => $row->url,
				'priority' => $priority,
				'due_at'   => $dueAt,
			];
		}
		$interrupted = $rows[ 1 ];
		$this->assertSame( 0, $interrupted->priority );
		$this->assertTrue( $repo->startNotificationAttempt( $interrupted, $now - 61 ) );
		$this->assertSame(
			1,
			( new NotificationMetadata() )->attemptsStarted( $repo->findById( $interrupted->id, true )->meta )
		);
		$rankedRows = \array_values( \array_filter(
			$rankedRows,
			static fn( array $ranked ) :bool => $ranked[ 'id' ] !== $interrupted->id
		) );
		\usort( $rankedRows, static function ( array $left, array $right ) :int {
			return $right[ 'priority' ] <=> $left[ 'priority' ]
				?: $left[ 'due_at' ] <=> $right[ 'due_at' ]
				?: $left[ 'id' ] <=> $right[ 'id' ];
		} );
		$expectedOrder = \array_merge( [ $interrupted->url ], \array_column( $rankedRows, 'url' ) );

		$clock = (object)[
			'now' => 100.0,
		];
		$order = [];
		$interruptedCountersAtSend = [];
		$afterSend = static function ( int $count, string $url = '' ) use (
			$clock,
			$interrupted,
			$repo,
			&$interruptedCountersAtSend,
			&$order
		) :void {
			$order[] = $url;
			if ( $url === $interrupted->url ) {
				$interruptedCountersAtSend[] = ( new NotificationMetadata() )->attemptsStarted(
					$repo->findById( $interrupted->id, true )->meta
				);
			}
			$clock->now += $url === 'https://mixed-notify-slow.example.com' ? 10.1 : 0.05;
		};
		$pingSender = new ImportExportPingSenderTestDouble(
			true,
			204,
			'',
			$afterSend,
			static fn( string $url ) :?array => $url === $failedURL ? [
				'success'   => false,
				'http_code' => 503,
				'error'     => 'service unavailable',
			] : null
		);
		$inviteSender = new ImportExportInviteSenderTestDouble(
			\array_fill( 0, 6, InvitationMetadata::RESULT_HTTP_RESPONSE ),
			204,
			$afterSend
		);
		$workers = 0;
		$successorDispatches = [];
		do {
			$clock->now += 1.0;
			$processor = new ImportExportQueueProcessorTestDouble(
				$pingSender,
				$inviteSender,
				$repo,
				static fn() :float => $clock->now
			);
			$processor->runFromCron();
			$workers++;
			$hasMoreWork = $repo->hasActionableWork();
			$this->assertSame( $hasMoreWork ? 1 : 0, $processor->dispatches );
			$successorDispatches[] = $processor->dispatches;
		} while ( $hasMoreWork && $workers < 20 );

		$this->assertSame( $expectedOrder, $order );
		$this->assertSame( [ 2 ], $interruptedCountersAtSend );
		$this->assertSame(
			0,
			( new NotificationMetadata() )->attemptsStarted( $repo->findById( $interrupted->id, true )->meta )
		);
		$this->assertGreaterThan( 1, $workers );
		$this->assertSame( $workers - 1, \array_sum( $successorDispatches ) );
		$this->assertFalse( $repo->hasActionableWork() );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findByUrl( $failedURL )->queue_status );
		$this->assertGreaterThan( Services::Request()->ts(), $repo->findByUrl( $failedURL )->next_ping_at );
		$statusCounts = [
			SitesDB::QUEUE_WAITING_EXPORT    => 0,
			SitesDB::QUEUE_PENDING_CONNECTION => 0,
			SitesDB::QUEUE_QUEUED             => 0,
		];
		foreach ( $rows as $row ) {
			$persisted = $repo->findById( $row->id, true );
			$this->assertArrayHasKey( $persisted->queue_status, $statusCounts );
			$statusCounts[ $persisted->queue_status ]++;
		}
		$this->assertSame( 293, $statusCounts[ SitesDB::QUEUE_WAITING_EXPORT ] );
		$this->assertSame( 6, $statusCounts[ SitesDB::QUEUE_PENDING_CONNECTION ] );
		$this->assertSame( 1, $statusCounts[ SitesDB::QUEUE_QUEUED ] );
	}

	public function test_queue_processor_retries_failed_invites_on_bounded_schedule() :void {
		$this->setRequestTimestamp( 1712620800 );
		$repo = $this->repo();
		$row = $repo->upsertPendingClientSite( 'https://invite-queued.example.com', SitesDB::SOURCE_MANUAL, true );
		$inviteSender = new ImportExportInviteSenderTestDouble( [
			[ InvitationMetadata::RESULT_TRANSPORT_FAILURE, 0 ],
			[ InvitationMetadata::RESULT_HTTP_FAILURE, 500 ],
			[ InvitationMetadata::RESULT_SENDER_FAILURE, 0 ],
		] );
		$runner = new ImportExportQueueProcessorTestDouble(
			new ImportExportPingSenderTestDouble( true, 204, '' ),
			$inviteSender
		);

		$runner->runFromCron();
		$row = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_PENDING_INVITE, $row->queue_status );
		$this->assertSame( 1712621700, $row->next_ping_at );
		$this->assertSame( 1, ( new InvitationMetadata() )->normalize( $row->meta )[ 'attempts_started' ] );

		$this->setRequestTimestamp( 1712621699 );
		$runner->runFromCron();
		$this->assertCount( 1, $inviteSender->urls );

		$this->setRequestTimestamp( 1712621700 );
		$runner->runFromCron();
		$row = $repo->findById( $row->id, true );
		$this->assertSame( 1712623500, $row->next_ping_at );
		$this->assertSame( 2, ( new InvitationMetadata() )->normalize( $row->meta )[ 'attempts_started' ] );

		$this->setRequestTimestamp( 1712623500 );
		$runner->runFromCron();
		$this->setRequestTimestamp( 1712625000 );
		$runner->runFromCron();

		$row = $repo->findById( $row->id, true );
		$this->assertCount( 3, $inviteSender->urls );
		$this->assertSame( [ 2, 2, 2 ], $inviteSender->timeouts );
		$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $row->queue_status );
		$this->assertSame( 0, $row->next_ping_at );
		$this->assertSame( 0, $row->last_ping_attempt_at );
		$this->assertSame( 3, ( new InvitationMetadata() )->normalize( $row->meta )[ 'attempts_started' ] );
	}

	public function test_queue_processor_treats_successful_http_response_as_unconfirmed_connection() :void {
		$repo = $this->repo();
		$row = $repo->upsertPendingClientSite( 'https://invite-http.example.com', SitesDB::SOURCE_MANUAL, true );
		$sender = new ImportExportInviteSenderTestDouble( [ InvitationMetadata::RESULT_HTTP_RESPONSE ], 204 );

		( new ImportExportQueueProcessorTestDouble( new ImportExportPingSenderTestDouble( true, 204, '' ), $sender ) )->runFromCron();

		$row = $repo->findById( $row->id, true );
		$invitation = ( new InvitationMetadata() )->normalize( $row->meta );
		$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $row->queue_status );
		$this->assertSame( InvitationMetadata::RESULT_HTTP_RESPONSE, $invitation[ 'last_result' ] );
		$this->assertSame( 204, $invitation[ 'last_http_status' ] );
	}

	public function test_interrupted_third_attempt_settles_without_a_fourth_send() :void {
		$this->setRequestTimestamp( 1712620800 );
		$repo = $this->repo();
		$row = $repo->upsertPendingClientSite( 'https://invite-interrupted.example.com', SitesDB::SOURCE_MANUAL, true );
		foreach ( [ 1, 2 ] as $attempt ) {
			$claimed = $repo->selectNextDueWork();
			$this->assertSame( 1, $repo->startInviteAttempt( $claimed, Services::Request()->ts() ) );
			$started = $repo->findById( $row->id, true );
			$this->assertSame( 1, $repo->recordInviteResult( $started, InvitationMetadata::RESULT_TRANSPORT_FAILURE ) );
			$this->setRequestTimestamp( $started->next_ping_at );
		}

		$settlementAt = Services::Request()->ts() + QueueProcessor::INVITE_PROCESSING_DEADLINE;
		$claimed = $repo->selectNextDueWork();
		$this->assertSame( 1, $repo->startInviteAttempt( $claimed, Services::Request()->ts() ) );
		$this->assertSame( $settlementAt, $repo->findById( $row->id, true )->next_ping_at );
		$this->setRequestTimestamp( $settlementAt );
		$sender = new ImportExportInviteSenderTestDouble();
		( new ImportExportQueueProcessorTestDouble( new ImportExportPingSenderTestDouble( true, 204, '' ), $sender ) )->runFromCron();

		$row = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $row->queue_status );
		$this->assertSame( InvitationMetadata::RESULT_STARTED, ( new InvitationMetadata() )->normalize( $row->meta )[ 'last_result' ] );
		$this->assertSame( [], $sender->urls );
	}

	public function test_active_pending_authorisation_resubmission_preserves_invitation_cycle() :void {
		$repo = $this->repo();
		$row = $repo->upsertPendingClientSite( 'https://invite-resubmit.example.com', SitesDB::SOURCE_MANUAL, true );
		$meta = $row->meta;
		$invitation = ( new InvitationMetadata() )->normalize( $meta );
		$invitation[ 'attempts_started' ] = InvitationMetadata::MAX_ATTEMPTS;
		$invitation[ 'last_attempt_started_at' ] = Services::Request()->ts();
		$invitation[ 'last_result' ] = InvitationMetadata::RESULT_HTTP_FAILURE;
		$invitation[ 'last_http_status' ] = 500;
		$meta = ( new InvitationMetadata() )->replace( $meta, $invitation );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
			'queue_status' => SitesDB::QUEUE_PENDING_CONNECTION,
			'next_ping_at' => 0,
			'meta'         => $this->requireController()->db_con->import_export_sites->getRecord()->arrayDataWrap( $meta ) ?? '',
		] );
		$before = $repo->findById( $row->id, true )->getRawData();

		$repo->upsertPendingClientSite( $row->url, SitesDB::SOURCE_MANUAL, false );
		$repo->upsertPendingClientSite( $row->url, SitesDB::SOURCE_MANUAL, true );

		$this->assertSame( $before, $repo->findById( $row->id, true )->getRawData() );
	}

	public function test_legacy_pending_invite_without_metadata_starts_at_attempt_one() :void {
		$repo = $this->repo();
		$row = $repo->upsertPendingClientSite( 'https://invite-legacy.example.com', SitesDB::SOURCE_MANUAL, true );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
			'meta' => $this->requireController()->db_con->import_export_sites->getRecord()->arrayDataWrap( [ 'preserved' => true ] ) ?? '',
		] );

		( new ImportExportQueueProcessorTestDouble(
			new ImportExportPingSenderTestDouble( true, 204, '' ),
			new ImportExportInviteSenderTestDouble( [ InvitationMetadata::RESULT_TRANSPORT_FAILURE ], 0 )
		) )->runFromCron();

		$meta = $repo->findById( $row->id, true )->meta;
		$invitation = ( new InvitationMetadata() )->normalize( $meta );
		$this->assertSame( 1, $invitation[ 'attempts_started' ] );
		$this->assertNotSame( '', $invitation[ 'cycle_id' ] );
		$this->assertTrue( $meta[ 'preserved' ] );
	}

	public function test_disabled_scheduler_hook_preserves_due_retry_and_reenable_resumes_cycle() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$start = 1712620800;
		$this->setRequestTimestamp( $start );
		$repo = $this->repo();
		$row = $repo->upsertPendingClientSite( 'https://example.com', SitesDB::SOURCE_MANUAL, true );
		$firstSender = new ImportExportInviteSenderTestDouble( [ InvitationMetadata::RESULT_TRANSPORT_FAILURE ], 0 );
		( new ImportExportQueueProcessorTestDouble( new ImportExportPingSenderTestDouble( true, 204, '' ), $firstSender ) )->runFromCron();
		$paused = $repo->findById( $row->id, true );
		$pausedRaw = $paused->getRawData();
		$pausedCycleID = ( new InvitationMetadata() )->normalize( $paused->meta )[ 'cycle_id' ];
		$httpRequests = 0;
		$httpResponse = static function ( $preempt ) use ( &$httpRequests ) :array {
			$httpRequests++;
			return [
				'headers'  => [],
				'body'     => '',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};
		$canRun = fn() :bool => ( new ImportExportController() )->isSyncEnabled();
		$processor = new QueueProcessor( $canRun );
		$scheduler = new QueueScheduler( $canRun, fn() => $processor->runFromCron() );
		$scheduler->setup();
		\add_filter( 'pre_http_request', $httpResponse, 10, 3 );

		try {
			$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();
			$this->setRequestTimestamp( $start + 30*\MINUTE_IN_SECONDS );
			\do_action( $scheduler->hook() );
			$this->assertSame( 0, $httpRequests );
			$this->assertSame( $pausedRaw, $repo->findById( $row->id, true )->getRawData() );

			$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
			$this->assertTrue( ( new ImportExportController() )->isSyncEnabled() );
			$this->assertNotFalse( \has_action( $scheduler->hook() ) );
			\do_action( $scheduler->hook() );
		}
		finally {
			\remove_filter( 'pre_http_request', $httpResponse, 10 );
		}

		$row = $repo->findById( $row->id, true );
		$invitation = ( new InvitationMetadata() )->normalize( $row->meta );
		$this->assertSame( 1, $httpRequests );
		$this->assertSame( 2, $invitation[ 'attempts_started' ] );
		$this->assertSame( $pausedCycleID, $invitation[ 'cycle_id' ] );
		$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $row->queue_status );
	}

	#[DataProvider( 'provideCooldownMetadataWriters' )]
	public function test_stale_cooldown_metadata_writer_preserves_final_attempt_and_prevents_fourth_send(
		string $writer,
		string $cooldownKey
	) :void {
		$this->setRequestTimestamp( 1712620800 );
		$repo = $this->repo();
		$row = $repo->upsertPendingClientSite( "https://invite-stale-{$writer}.example.com", SitesDB::SOURCE_MANUAL, true );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
			'meta' => $this->requireController()->db_con->import_export_sites->getRecord()->arrayDataWrap( [
				'preserved' => true,
			] ) ?? '',
		] );
		foreach ( [ 1, 2 ] as $attempt ) {
			$claimed = $repo->selectNextDueWork();
			$this->assertSame( 1, $repo->startInviteAttempt( $claimed, Services::Request()->ts() ) );
			$started = $repo->findById( $row->id, true );
			$this->assertSame( 1, $repo->recordInviteResult( $started, InvitationMetadata::RESULT_TRANSPORT_FAILURE ) );
			$this->setRequestTimestamp( $started->next_ping_at );
		}

		$staleCooldownRow = $repo->findById( $row->id, true );
		$settlementAt = Services::Request()->ts() + QueueProcessor::INVITE_PROCESSING_DEADLINE;
		$claimed = $repo->selectNextDueWork();
		$this->assertSame( 1, $repo->startInviteAttempt( $claimed, Services::Request()->ts() ) );
		$thirdAttempt = $repo->findById( $row->id, true );
		$thirdAttemptCycleID = ( new InvitationMetadata() )->normalize( $thirdAttempt->meta )[ 'cycle_id' ];
		$cooldownTimestamp = Services::Request()->ts();

		$repo->{$writer}( $staleCooldownRow );

		$afterCooldown = $repo->findById( $row->id, true );
		$afterCooldownInvitation = ( new InvitationMetadata() )->normalize( $afterCooldown->meta );
		$this->assertSame( $cooldownTimestamp, $afterCooldown->meta[ $cooldownKey ] );
		$this->assertTrue( $afterCooldown->meta[ 'preserved' ] );
		$this->assertSame( $thirdAttemptCycleID, $afterCooldownInvitation[ 'cycle_id' ] );
		$this->assertSame( 3, $afterCooldownInvitation[ 'attempts_started' ] );
		$this->assertSame( 0, $repo->recordInviteResult( $thirdAttempt, InvitationMetadata::RESULT_TRANSPORT_FAILURE ) );
		$this->setRequestTimestamp( $settlementAt );
		$sender = new ImportExportInviteSenderTestDouble();
		( new ImportExportQueueProcessorTestDouble(
			new ImportExportPingSenderTestDouble( true, 204, '' ),
			$sender
		) )->runFromCron();

		$settled = $repo->findById( $row->id, true );
		$this->assertSame( [], $sender->urls );
		$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $settled->queue_status );
		$this->assertSame( 3, ( new InvitationMetadata() )->normalize( $settled->meta )[ 'attempts_started' ] );
	}

	public static function provideCooldownMetadataWriters() :array {
		return [
			'handshake attempt' => [ 'recordHandshakeAttempt', 'handshake_attempt_at' ],
			'export served'     => [ 'recordExportServed', 'export_served_at' ],
		];
	}

	public function test_superseded_attempt_cannot_consume_or_overwrite_manual_restart_cycle() :void {
		$repo = new ImportExportRestartAfterStartRepositoryTestDouble();
		$row = $repo->upsertPendingClientSite( 'https://invite-snapshot.example.com', SitesDB::SOURCE_MANUAL, true );
		$oldCycleID = ( new InvitationMetadata() )->normalize( $row->meta )[ 'cycle_id' ];
		$sender = new ImportExportInviteSenderTestDouble();

		( new ImportExportQueueProcessorTestDouble(
			new ImportExportPingSenderTestDouble( true, 204, '' ),
			$sender,
			$repo
		) )->runFromCron();

		$this->assertSame( [ $row->url ], $sender->urls );
		$current = $this->repo()->findById( $row->id, true );
		$invitation = ( new InvitationMetadata() )->normalize( $current->meta );
		$this->assertSame( SitesDB::QUEUE_PENDING_INVITE, $current->queue_status );
		$this->assertSame( 0, $invitation[ 'attempts_started' ] );
		$this->assertSame( $repo->restartCycleID, $invitation[ 'cycle_id' ] );
		$this->assertNotSame( $oldCycleID, $invitation[ 'cycle_id' ] );
	}

	public function test_each_attempt_uses_its_actual_start_time_for_backoff() :void {
		$start = 1712620800;
		$this->setRequestTimestamp( $start );
		$repo = $this->repo();
		$first = $repo->upsertPendingClientSite( 'https://invite-clock-one.example.com', SitesDB::SOURCE_MANUAL, true );
		$second = $repo->upsertPendingClientSite( 'https://invite-clock-two.example.com', SitesDB::SOURCE_MANUAL, true );
		$sender = new ImportExportInviteSenderTestDouble(
			[ InvitationMetadata::RESULT_TRANSPORT_FAILURE, InvitationMetadata::RESULT_TRANSPORT_FAILURE ],
			0,
			function ( int $sendCount ) use ( $start ) :void {
				if ( $sendCount === 1 ) {
					$this->setRequestTimestamp( $start + 60 );
				}
			}
		);

		( new ImportExportQueueProcessorTestDouble( new ImportExportPingSenderTestDouble( true, 204, '' ), $sender ) )->runFromCron();

		$this->assertSame( $start + 15*\MINUTE_IN_SECONDS, $repo->findById( $first->id, true )->next_ping_at );
		$this->assertSame( $start + 60 + 15*\MINUTE_IN_SECONDS, $repo->findById( $second->id, true )->next_ping_at );
	}

	public function test_claim_only_does_not_start_an_invitation_attempt() :void {
		$repo = $this->repo();
		$row = $repo->upsertPendingClientSite( 'https://invite-claim-only.example.com', SitesDB::SOURCE_MANUAL, true );

		$selected = $repo->selectNextDueWork();

		$persisted = $repo->findById( $row->id, true );
		$this->assertSame( $row->id, $selected->id );
		$this->assertSame( 0, ( new InvitationMetadata() )->normalize( $persisted->meta )[ 'attempts_started' ] );
		$this->assertSame( 0, $persisted->lock_until );
	}

	public function test_failed_attempt_start_sends_nothing_and_stops_the_worker() :void {
		global $wpdb;
		$repo = $this->repo();
		for ( $i = 0; $i < 5; $i++ ) {
			$repo->upsertPendingClientSite( "https://invite-failed-start-{$i}.example.com", SitesDB::SOURCE_MANUAL, true );
		}
		$sync = $repo->upsertActive( 'https://sync-after-failed-starts.example.com', SitesDB::SOURCE_MANUAL, 'sync-id', true );
		$inviteSender = new ImportExportInviteSenderTestDouble();
		$pingSender = new ImportExportPingSenderTestDouble( true, 204, '' );
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$queryFailure = static function ( string $query ) use ( $table ) :string {
			return \strpos( $query, "UPDATE `{$table}`" ) !== false && \strpos( $query, 'BINARY `meta`' ) !== false
				? 'UPDATE intentionally_invalid_invitation_sql'
				: $query;
		};
		\add_filter( 'query', $queryFailure, 1000 );
		$previousShowErrors = $wpdb->hide_errors();
		$previousSuppressErrors = $wpdb->suppress_errors( true );

		try {
			( new ImportExportQueueProcessorTestDouble( $pingSender, $inviteSender, $repo ) )->runFromCron();
		}
		finally {
			\remove_filter( 'query', $queryFailure, 1000 );
			$wpdb->show_errors( $previousShowErrors );
			$wpdb->suppress_errors( $previousSuppressErrors );
		}

		$this->assertSame( [], $inviteSender->urls );
		$this->assertSame( [], $pingSender->importIDs );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $this->repo()->findById( $sync->id, true )->queue_status );
	}

	public function test_failed_result_persistence_retains_started_snapshot_and_due_time() :void {
		$start = 1712620800;
		$this->setRequestTimestamp( $start );
		$repo = new ImportExportFailedResultRepositoryTestDouble();
		$row = $repo->upsertPendingClientSite( 'https://invite-result-write.example.com', SitesDB::SOURCE_MANUAL, true );

		( new ImportExportQueueProcessorTestDouble(
			new ImportExportPingSenderTestDouble( true, 204, '' ),
			new ImportExportInviteSenderTestDouble( [ InvitationMetadata::RESULT_TRANSPORT_FAILURE ], 0 ),
			$repo
		) )->runFromCron();

		$row = $this->repo()->findById( $row->id, true );
		$invitation = ( new InvitationMetadata() )->normalize( $row->meta );
		$this->assertSame( InvitationMetadata::RESULT_STARTED, $invitation[ 'last_result' ] );
		$this->assertSame( 1, $invitation[ 'attempts_started' ] );
		$this->assertSame( $start + 15*\MINUTE_IN_SECONDS, $row->next_ping_at );
	}

	public function test_stale_invitation_writes_cannot_override_pull_delete_or_new_cycle() :void {
		$repo = $this->repo();
		$rows = [];
		foreach ( [ 'pull', 'delete', 'cycle' ] as $case ) {
			$row = $repo->upsertPendingClientSite( "https://invite-superseded-{$case}.example.com", SitesDB::SOURCE_MANUAL, true );
			$claimed = $repo->selectNextDueWork();
			$this->assertSame( 1, $repo->startInviteAttempt( $claimed, Services::Request()->ts() ) );
			$rows[ $case ] = $claimed;
		}

		$repo->recordExportSuccess( $rows[ 'pull' ], SitesDB::EXPORT_RESULT_SUCCESS, 'fresh-id' );
		$repo->softDeleteUrl( $rows[ 'delete' ]->url );
		$this->assertSame( 1, $repo->recordInviteResult( $rows[ 'cycle' ], InvitationMetadata::RESULT_HTTP_RESPONSE, 200 ) );
		$this->assertSame( 1, $repo->restartInvitationsByIds( [ $rows[ 'cycle' ]->id ] )[ 'queued_count' ] );

		foreach ( $rows as $row ) {
			$this->assertSame( 0, $repo->recordInviteResult( $row, InvitationMetadata::RESULT_HTTP_FAILURE, 500 ) );
		}
		$this->assertSame( SitesDB::QUEUE_IDLE, $repo->findById( $rows[ 'pull' ]->id, true )->queue_status );
		$this->assertSame( SitesDB::STATUS_DELETED, $repo->findById( $rows[ 'delete' ]->id, true )->status );
		$this->assertSame( 0, ( new InvitationMetadata() )->normalize( $repo->findById( $rows[ 'cycle' ]->id, true )->meta )[ 'attempts_started' ] );
	}

	public function test_invitation_compare_and_swap_uses_binary_raw_metadata() :void {
		$repo = $this->repo();
		$row = $repo->upsertPendingClientSite( 'https://invite-binary-cas.example.com', SitesDB::SOURCE_MANUAL, true );
		$claimed = $repo->selectNextDueWork();
		$rawMeta = (string)$claimed->getRawData()[ 'meta' ];
		$caseChanged = \strtr( $rawMeta, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [ 'meta' => $caseChanged ] );

		$this->assertSame( 0, $repo->startInviteAttempt( $claimed, Services::Request()->ts() ) );
	}

	public function test_sync_observation_slots_replace_independently_and_preserve_existing_metadata() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://diagnostic-slots.example.com', SitesDB::SOURCE_MANUAL );
		$this->assertInstanceOf( Record::class, $row );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
			'meta' => $this->requireController()->db_con->import_export_sites->getRecord()->arrayDataWrap( [
				'preserved' => 'value',
			] ) ?? '',
		] );

		$row = $repo->findById( $row->id, true );
		$notification = SyncObservation::create( 100, SyncObservation::PHASE_NOTIFICATION,
			SyncObservation::RESULT_NO_HTTP_RESPONSE, SyncObservation::VERIFICATION_NOT_APPLICABLE );
		$verification = SyncObservation::create( 200, SyncObservation::PHASE_VERIFICATION,
			SyncObservation::RESULT_MISSING_ID, SyncObservation::VERIFICATION_FAILED );
		$newNotification = SyncObservation::create( 300, SyncObservation::PHASE_NOTIFICATION,
			SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED, SyncObservation::VERIFICATION_NOT_APPLICABLE,
			[ 'http_status' => 204 ] );

		$this->assertTrue( $repo->saveObservation( $row, SyncObservation::PHASE_NOTIFICATION, $notification ) );
		$this->assertTrue( $repo->saveObservation( $row, SyncObservation::PHASE_VERIFICATION, $verification ) );
		$this->assertTrue( $repo->saveObservation( $row, SyncObservation::PHASE_NOTIFICATION, $newNotification ) );

		$fresh = $repo->findById( $row->id, true );
		$this->assertSame( 'value', $fresh->meta[ 'preserved' ] );
		$this->assertSame( $newNotification, $repo->readObservation( $fresh, SyncObservation::PHASE_NOTIFICATION ) );
		$this->assertSame( $verification, $repo->readObservation( $fresh, SyncObservation::PHASE_VERIFICATION ) );
	}

	public function test_sync_observation_read_rejects_phase_mismatched_to_slot() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://observation-phase-mismatch.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$meta = $row->meta;
		$meta[ 'sync_observations' ][ SyncObservation::PHASE_NOTIFICATION ] = SyncObservation::create(
			100,
			SyncObservation::PHASE_EXPORT,
			SyncObservation::RESULT_EXPORT_SERVED,
			SyncObservation::VERIFICATION_ESTABLISHED
		);

		$row->meta = $meta;

		$this->assertNull( $repo->readObservation( $row, SyncObservation::PHASE_NOTIFICATION ) );
	}

	public function test_sync_observation_write_rejects_deleted_rows_and_wrong_slots() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://diagnostic-deleted.example.com', SitesDB::SOURCE_MANUAL );
		$observation = SyncObservation::create( 100, SyncObservation::PHASE_NOTIFICATION,
			SyncObservation::RESULT_NO_HTTP_RESPONSE, SyncObservation::VERIFICATION_NOT_APPLICABLE );

		$this->assertFalse( $repo->saveObservation( $row, SyncObservation::PHASE_EXPORT, $observation ) );
		$repo->softDeleteUrl( $row->url );
		$this->assertFalse( $repo->saveObservation( $row, SyncObservation::PHASE_NOTIFICATION, $observation ) );
	}

	public function test_sync_observation_save_preserves_newer_persisted_metadata_without_mutating_caller_snapshot() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://diagnostic-stale-caller.example.com', SitesDB::SOURCE_MANUAL );
		$this->assertInstanceOf( Record::class, $row );
		$callerMeta = $row->meta;
		$servedAt = Services::Request()->ts();
		$persistedMeta = \is_array( $callerMeta ) ? $callerMeta : [];
		$persistedMeta[ 'export_served_at' ] = $servedAt;
		$persistedMeta[ 'newer_value' ] = 'preserved';
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
			'meta' => $this->requireController()->db_con->import_export_sites->getRecord()->arrayDataWrap( $persistedMeta ) ?? '',
		] );
		$observation = SyncObservation::create( $servedAt, SyncObservation::PHASE_VERIFICATION,
			SyncObservation::RESULT_VERIFICATION_PASSED, SyncObservation::VERIFICATION_ESTABLISHED );

		$this->assertTrue( $repo->saveObservation( $row, SyncObservation::PHASE_VERIFICATION, $observation ) );

		$this->assertSame( $callerMeta, $row->meta );
		$fresh = $repo->findById( $row->id, true );
		$this->assertSame( $servedAt, $fresh->meta[ 'export_served_at' ] );
		$this->assertSame( 'preserved', $fresh->meta[ 'newer_value' ] );
		$this->assertSame( $observation, $repo->readObservation( $fresh, SyncObservation::PHASE_VERIFICATION ) );
	}

	public function test_upserts_repair_invalid_existing_profile_refs() :void {
		$profile = ( new ProfileRepository() )->ensureDefaultProfile();
		$this->assertNotEmpty( $profile );
		$repo = $this->repo();

		$active = $repo->upsertActive( 'https://profile-repair-active.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$pending = $repo->upsertPendingClientSite( 'https://profile-repair-pending.example.com', SitesDB::SOURCE_MANUAL, true );
		$orphanProfileRef = $profile->id + 10000;
		foreach ( [ $active, $pending ] as $row ) {
			$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
				'profile_ref' => $orphanProfileRef,
			] );
			$this->assertSame( $orphanProfileRef, $repo->findById( $row->id, true )->profile_ref );
		}

		$repo->upsertActive( $active->url, SitesDB::SOURCE_MANUAL, '', false );
		$repo->upsertPendingClientSite( $pending->url, SitesDB::SOURCE_MANUAL, false );

		$this->assertSame( $profile->id, $repo->findById( $active->id, true )->profile_ref );
		$this->assertSame( $profile->id, $repo->findById( $pending->id, true )->profile_ref );
	}

	public function test_queue_processor_leaves_passive_pending_connection_unsent() :void {
		$repo = $this->repo();
		$row = $repo->upsertPendingClientSite( 'https://invite-passive.example.com', SitesDB::SOURCE_MANUAL, false );
		$inviteSender = new ImportExportInviteSenderTestDouble();

		( new ImportExportQueueProcessorTestDouble(
			new ImportExportPingSenderTestDouble( true, 204, '' ),
			$inviteSender
		) )->runFromCron();

		$row = $repo->findById( $row->id, true );
		$this->assertSame( [], $inviteSender->urls );
		$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $row->queue_status );
		$this->assertSame( 0, $row->last_ping_attempt_at );
	}

	public function test_expired_processing_row_remains_persisted_for_recovery() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://recover-processing.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $row, Services::Request()->ts() - 61 ) );
		$recovered = $repo->selectNextInterruptedNotification();

		$row = $repo->findById( $row->id, true );
		$this->assertSame( $row->id, $recovered->id );
		$this->assertSame( SitesDB::QUEUE_PROCESSING, $row->queue_status );
		$this->assertLessThanOrEqual( Services::Request()->ts(), $row->lock_until );
	}

	public function test_manual_queue_skips_both_pending_states_without_scheduling() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		$repo = $this->repo();
		$pendingInvite = $repo->upsertPendingClientSite( 'https://manual-invite.example.com', SitesDB::SOURCE_MANUAL, true );
		$pendingConnection = $repo->upsertPendingClientSite( 'https://manual-connection.example.com', SitesDB::SOURCE_MANUAL, false );
		$pendingInviteNextPing = $pendingInvite->next_ping_at;
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );

		$count = ( new ImportExportController() )->queueSitesForSync( [ $pendingInvite->id, $pendingConnection->id ] );

		$pendingInvite = $repo->findById( $pendingInvite->id, true );
		$pendingConnection = $repo->findById( $pendingConnection->id, true );
		$this->assertSame( 0, $count );
		$this->assertSame( SitesDB::QUEUE_PENDING_INVITE, $pendingInvite->queue_status );
		$this->assertSame( $pendingInviteNextPing, $pendingInvite->next_ping_at );
		$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $pendingConnection->queue_status );
		$this->assertSame( 0, $pendingConnection->next_ping_at );
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_failed_ping_records_ping_failure_without_export_success() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://fail-ping.example.com', SitesDB::SOURCE_MANUAL, '', true );

		( new ImportExportQueueProcessorTestDouble( new ImportExportPingSenderTestDouble( false, 503, 'service unavailable' ) ) )->runFromCron();

		$row = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $row->queue_status );
		$this->assertGreaterThan( 0, $row->last_ping_failure_at );
		$this->assertSame( 503, $row->last_ping_http_code );
		$this->assertSame( 'service unavailable', $row->last_ping_error );
		$this->assertSame( 0, $row->last_export_success_at );
	}

	public function test_queue_processor_records_attempted_notify_without_response_as_waiting_for_export() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://notify-no-response.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$observation = SyncObservation::create( Services::Request()->ts(), SyncObservation::PHASE_NOTIFICATION,
			SyncObservation::RESULT_NO_HTTP_RESPONSE, SyncObservation::VERIFICATION_NOT_APPLICABLE );

		( new ImportExportQueueProcessorTestDouble( new ImportExportPingSenderTestDouble(
			true,
			0,
			'',
			null,
			static fn() :array => [
				'success'     => true,
				'http_code'   => 0,
				'error'       => '',
				'observation' => $observation,
			]
		) ) )->runFromCron();

		$row = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_WAITING_EXPORT, $row->queue_status );
		$this->assertGreaterThan( 0, $row->last_ping_success_at );
		$this->assertSame( 0, $row->last_ping_http_code );
		$this->assertSame( '', $row->last_ping_error );
		$this->assertGreaterThan( Services::Request()->ts(), $row->expected_export_by );
		$this->assertSame( 0, $row->last_export_failure_at );
		$this->assertSame( $observation, $repo->readObservation( $row, SyncObservation::PHASE_NOTIFICATION ) );
	}

	#[DataProvider( 'provideQueueNotificationOutcomes' )]
	public function test_queue_processor_persists_notification_outcome_without_changing_queue_semantics(
		string $case,
		bool $success,
		int $httpCode,
		string $error,
		string $result,
		bool $throws
	) :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$row = $repo->upsertActive( "https://notify-outcome-{$case}.example.com", SitesDB::SOURCE_MANUAL, '', true );
		$observation = SyncObservation::create(
			$now,
			SyncObservation::PHASE_NOTIFICATION,
			$result,
			SyncObservation::VERIFICATION_NOT_APPLICABLE,
			$httpCode > 0 ? [ 'http_status' => $httpCode ] : []
		);
		$sender = new ImportExportPingSenderTestDouble(
			$success,
			$httpCode,
			$error,
			null,
			$throws
				? static function () :array {
					throw new \RuntimeException( 'sender detail must not escape' );
				}
				: static fn() :array => [
					'success'     => $success,
					'http_code'   => $httpCode,
					'error'       => $error,
					'observation' => $observation,
				]
		);

		( new ImportExportQueueProcessorTestDouble( $sender, null, $repo ) )->runFromCron();

		$fresh = $repo->findById( $row->id, true );
		$this->assertCount( 1, $sender->urls );
		$this->assertSame( $success && !$throws ? SitesDB::QUEUE_WAITING_EXPORT : SitesDB::QUEUE_QUEUED, $fresh->queue_status );
		$this->assertSame( $throws ? 0 : $httpCode, $fresh->last_ping_http_code );
		$this->assertSame( $success && !$throws ? '' : ( $throws ? 'Notification sender failed.' : $error ), $fresh->last_ping_error );
		$this->assertSame( $success && !$throws ? $now : 0, $fresh->last_ping_success_at );
		$this->assertSame( $success && !$throws ? 0 : $now, $fresh->last_ping_failure_at );
		$this->assertSame( $success && !$throws ? 0 : 1, $fresh->consecutive_failures );
		$this->assertSame( $success && !$throws ? $now + QueueProcessor::EXPORT_GRACE : 0, $fresh->expected_export_by );
		$this->assertSame( $success && !$throws ? $now : $now + 15*\MINUTE_IN_SECONDS, $fresh->next_ping_at );
		$stored = $repo->readObservation( $fresh, SyncObservation::PHASE_NOTIFICATION );
		$this->assertSame( $result, $stored[ 'result' ] ?? null );
		$this->assertSame( $httpCode > 0 && !$throws ? $httpCode : null, $stored[ 'http_status' ] ?? null );
	}

	public static function provideQueueNotificationOutcomes() :array {
		return [
			'http 200'       => [ '200', true, 200, '', SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED, false ],
			'http 403'       => [ '403', true, 403, '', SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED, false ],
			'http 500'       => [ '500', true, 500, '', SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED, false ],
			'no response'    => [ 'no-response', true, 0, '', SyncObservation::RESULT_NO_HTTP_RESPONSE, false ],
			'invalid target' => [ 'invalid-target', false, 0, 'invalid_url', SyncObservation::RESULT_LOCAL_TARGET_VALIDATION_FAILED, false ],
			'sender exception' => [ 'sender-exception', false, 0, '', SyncObservation::RESULT_SENDER_EXCEPTION, true ],
		];
	}

	#[DataProvider( 'provideLateNotificationOutcomes' )]
	public function test_completed_export_survives_late_notification_result( string $outcome ) :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://late-notification-'.$outcome.'.example.com', SitesDB::SOURCE_MANUAL, 'import-id', true );
		$meta = $row->meta;
		$priorObservation = SyncObservation::create( $now - 1, SyncObservation::PHASE_NOTIFICATION,
			SyncObservation::RESULT_NO_HTTP_RESPONSE, SyncObservation::VERIFICATION_NOT_APPLICABLE );
		$meta[ 'preserve_this' ] = 'preserved';
		$meta[ 'sync_observations' ][ SyncObservation::PHASE_NOTIFICATION ] = $priorObservation;
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
			'meta' => $this->requireController()->db_con->import_export_sites->getRecord()->arrayDataWrap( $meta ) ?? '',
		] );
		$row = $repo->findById( $row->id, true );
		$sender = new ImportExportPingSenderTestDouble(
			$outcome === 'success',
			$outcome === 'success' ? 204 : 503,
			$outcome === 'failure' ? 'service unavailable' : '',
			static function () use ( $repo, $row ) :void {
				$repo->recordExportSuccess( $repo->findById( $row->id, true ), SitesDB::EXPORT_RESULT_SUCCESS, 'completed-id' );
			},
			$outcome === 'exception'
				? static function () :array {
					throw new \RuntimeException( 'sender failed after export completed' );
				}
				: static fn() :array => [
					'success'     => $outcome === 'success',
					'http_code'   => $outcome === 'success' ? 204 : 503,
					'error'       => $outcome === 'failure' ? 'service unavailable' : '',
					'observation' => SyncObservation::create( $now, SyncObservation::PHASE_NOTIFICATION,
						SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED, SyncObservation::VERIFICATION_NOT_APPLICABLE,
						[ 'http_status' => $outcome === 'success' ? 204 : 503 ] ),
				]
		);

		( new ImportExportQueueProcessorTestDouble( $sender, null, $repo ) )->runFromCron();

		$completed = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_IDLE, $completed->queue_status );
		$this->assertSame( $now, $completed->last_export_success_at );
		$this->assertSame( SitesDB::EXPORT_RESULT_SUCCESS, $completed->last_export_result_code );
		$this->assertSame( 'completed-id', $completed->import_id );
		$this->assertSame( 0, $completed->consecutive_failures );
		$this->assertSame( $now + \DAY_IN_SECONDS, $completed->next_ping_at );
		$this->assertSame( 'preserved', $completed->meta[ 'preserve_this' ] ?? null );
		$this->assertSame( 1, ( new NotificationMetadata() )->attemptsStarted( $completed->meta ) );
		$this->assertSame( $priorObservation, $repo->readObservation( $completed, SyncObservation::PHASE_NOTIFICATION ) );
	}

	public static function provideLateNotificationOutcomes() :array {
		return [
			'success'   => [ 'success' ],
			'failure'   => [ 'failure' ],
			'exception' => [ 'exception' ],
		];
	}

	public function test_stale_timeout_and_exhaustion_transitions_apply_once_without_overwriting_success() :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();

		$timeout = $repo->upsertActive( 'https://stale-timeout.example.com', SitesDB::SOURCE_MANUAL, 'timeout-id', true );
		$this->assertTrue( $repo->startNotificationAttempt( $timeout, $now ) );
		$this->assertSame( 1, $repo->recordNotifyDispatched( $timeout, 204, $now - 1 ) );
		$timeout = $repo->findById( $timeout->id, true );
		$this->assertSame( 1, $repo->recordExportTimeout( $timeout ) );
		$this->assertSame( 0, $repo->recordExportTimeout( $timeout ) );
		$this->assertSame( 1, $repo->findById( $timeout->id, true )->consecutive_failures );

		$completedTimeout = $repo->upsertActive( 'https://completed-timeout.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $completedTimeout, $now ) );
		$this->assertSame( 1, $repo->recordNotifyDispatched( $completedTimeout, 204, $now - 1 ) );
		$selectedTimeout = $repo->findById( $completedTimeout->id, true );
		$repo->recordExportSuccess( $selectedTimeout, SitesDB::EXPORT_RESULT_SUCCESS, 'completed-timeout-id' );
		$this->assertSame( 0, $repo->recordExportTimeout( $selectedTimeout ) );
		$completedTimeout = $repo->findById( $completedTimeout->id, true );
		$this->assertSame( SitesDB::QUEUE_IDLE, $completedTimeout->queue_status );
		$this->assertSame( 'completed-timeout-id', $completedTimeout->import_id );
		$this->assertSame( 0, $completedTimeout->consecutive_failures );

		$exhausted = $repo->upsertActive( 'https://duplicate-exhaustion.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->startExhaustedNotification( $repo, $exhausted, $now );
		$exhausted = $repo->findById( $exhausted->id, true );
		$this->assertSame( 1, $repo->recordInterruptedNotificationExhaustion( $exhausted ) );
		$this->assertSame( 0, $repo->recordInterruptedNotificationExhaustion( $exhausted ) );
		$this->assertSame( 1, $repo->findById( $exhausted->id, true )->consecutive_failures );

		$completedExhaustion = $repo->upsertActive( 'https://completed-exhaustion.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->startExhaustedNotification( $repo, $completedExhaustion, $now );
		$selectedExhaustion = $repo->findById( $completedExhaustion->id, true );
		$repo->recordExportSuccess( $selectedExhaustion, SitesDB::EXPORT_RESULT_SUCCESS, 'completed-exhaustion-id' );
		$this->assertSame( 0, $repo->recordInterruptedNotificationExhaustion( $selectedExhaustion ) );
		$completedExhaustion = $repo->findById( $completedExhaustion->id, true );
		$this->assertSame( SitesDB::QUEUE_IDLE, $completedExhaustion->queue_status );
		$this->assertSame( 'completed-exhaustion-id', $completedExhaustion->import_id );
		$this->assertSame( 0, $completedExhaustion->consecutive_failures );
	}

	public function test_timeout_and_exhaustion_skip_removed_inactive_and_different_operations() :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		foreach ( [ 'timeout', 'exhaustion' ] as $transition ) {
			foreach ( [ 'removed', 'inactive', 'different' ] as $mutation ) {
				$url = "https://{$transition}-{$mutation}.example.com";
				$selected = $this->prepareTimeoutOrExhaustionCandidate( $repo, $transition, $url, $now );
				if ( $mutation === 'removed' ) {
					$this->assertSame( 1, $repo->deleteByIds( [ $selected->id ] ) );
					$before = null;
				}
				elseif ( $mutation === 'inactive' ) {
					$repo->softDeleteUrl( $url );
					$before = $repo->findById( $selected->id, true )->getRawData();
				}
				else {
					$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $selected->id, [
						'queue_status'       => SitesDB::QUEUE_IDLE,
						'picked_at'          => 0,
						'lock_until'         => 0,
						'expected_export_by' => 0,
					] );
					$before = $repo->findById( $selected->id, true )->getRawData();
				}

				$result = $transition === 'timeout'
					? $repo->recordExportTimeout( $selected )
					: $repo->recordInterruptedNotificationExhaustion( $selected );
				$this->assertSame( 0, $result, $transition.' '.$mutation );
				$after = $repo->findById( $selected->id, true );
				$this->assertSame( $before, $after instanceof Record ? $after->getRawData() : null );
			}
		}
	}

	public function test_notification_result_retries_one_metadata_conflict_and_preserves_unrelated_metadata() :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://notification-meta-retry.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $row, $now ) );
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$interleaved = false;
		$filter = function ( string $query ) use ( $repo, $row, $table, &$interleaved ) :string {
			if ( !$interleaved && \strpos( $query, "UPDATE `{$table}`" ) !== false && \strpos( $query, 'BINARY `meta`' ) !== false ) {
				$interleaved = true;
				$current = $repo->findById( $row->id, true );
				$meta = $current->meta;
				$meta[ 'concurrent_key' ] = 'preserved';
				$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
					'meta' => $this->requireController()->db_con->import_export_sites->getRecord()->arrayDataWrap( $meta ) ?? '',
				] );
			}
			return $query;
		};
		\add_filter( 'query', $filter, 1000 );
		try {
			$result = $repo->recordNotifyDispatched( $row, 204, $now + 600 );
		}
		finally {
			\remove_filter( 'query', $filter, 1000 );
		}

		$this->assertTrue( $interleaved );
		$this->assertSame( 1, $result );
		$persisted = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_WAITING_EXPORT, $persisted->queue_status );
		$this->assertSame( 'preserved', $persisted->meta[ 'concurrent_key' ] ?? null );
	}

	public function test_notification_result_stops_after_repeated_metadata_conflict() :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://notification-meta-conflict.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $row, $now ) );
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$conflicts = 0;
		$filter = function ( string $query ) use ( $repo, $row, $table, &$conflicts ) :string {
			if ( $conflicts < 2 && \strpos( $query, "UPDATE `{$table}`" ) !== false && \strpos( $query, 'BINARY `meta`' ) !== false ) {
				$conflicts++;
				$current = $repo->findById( $row->id, true );
				$meta = $current->meta;
				$meta[ 'conflict' ] = $conflicts;
				$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
					'meta' => $this->requireController()->db_con->import_export_sites->getRecord()->arrayDataWrap( $meta ) ?? '',
				] );
			}
			return $query;
		};
		\add_filter( 'query', $filter, 1000 );
		try {
			$result = $repo->recordPingFailure( $row, 503, 'service unavailable' );
		}
		finally {
			\remove_filter( 'query', $filter, 1000 );
		}

		$this->assertSame( 2, $conflicts );
		$this->assertFalse( $result );
		$persisted = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_PROCESSING, $persisted->queue_status );
		$this->assertSame( 0, $persisted->consecutive_failures );
	}

	#[DataProvider( 'notificationRefreshFailureProvider' )]
	public function test_notification_result_treats_conflict_refresh_query_failure_as_write_failure( int $failedSelect ) :void {
		global $wpdb;
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$row = $repo->upsertActive( "https://notification-refresh-failure-{$failedSelect}.example.com", SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $row, $now ) );
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$conflicts = 0;
		$selects = 0;
		$filter = function ( string $query ) use ( $row, $table, $failedSelect, &$conflicts, &$selects ) :string {
			if ( $conflicts < $failedSelect
				 && \strpos( $query, "UPDATE `{$table}`" ) !== false
				 && \strpos( $query, 'BINARY `meta`' ) !== false ) {
				$conflicts++;
				$meta = $row->meta;
				$meta[ 'conflict' ] = $conflicts;
				$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
					'meta' => $this->requireController()->db_con->import_export_sites->getRecord()->arrayDataWrap( $meta ) ?? '',
				] );
			}
			if ( \strpos( $query, "SELECT * FROM `{$table}` WHERE `id`={$row->id} LIMIT 1" ) !== false ) {
				$selects++;
				if ( $selects === $failedSelect ) {
					return 'SELECT intentionally_invalid_notification_refresh_sql';
				}
			}
			return $query;
		};
		$previousSuppressErrors = $wpdb->suppress_errors( true );
		\add_filter( 'query', $filter, 1000 );
		try {
			$result = $repo->recordNotifyDispatched( $row, 204, $now + 600 );
		}
		finally {
			\remove_filter( 'query', $filter, 1000 );
			$wpdb->suppress_errors( $previousSuppressErrors );
		}

		$this->assertSame( $failedSelect, $conflicts );
		$this->assertSame( $failedSelect, $selects );
		$this->assertFalse( $result );
		$persisted = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_PROCESSING, $persisted->queue_status );
	}

	public static function notificationRefreshFailureProvider() :array {
		return [
			'first refresh fails' => [ 1 ],
			'final classification fails' => [ 2 ],
		];
	}

	public function test_notification_result_skips_removed_inactive_and_different_operations() :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();

		$removed = $repo->upsertActive( 'https://notification-removed.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $removed, $now ) );
		$this->assertSame( 1, $repo->deleteByIds( [ $removed->id ] ) );
		$this->assertSame( 0, $repo->recordNotifyDispatched( $removed, 204, $now + 600 ) );
		$this->assertNull( $repo->findById( $removed->id, true ) );

		$inactive = $repo->upsertActive( 'https://notification-inactive.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $inactive, $now ) );
		$repo->softDeleteUrl( $inactive->url );
		$inactiveState = $repo->findById( $inactive->id, true )->getRawData();
		$this->assertSame( 0, $repo->recordPingFailure( $inactive, 503, 'late failure' ) );
		$this->assertSame( $inactiveState, $repo->findById( $inactive->id, true )->getRawData() );

		$different = $repo->upsertActive( 'https://notification-different-operation.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $different, $now ) );
		$oldOperation = clone $different;
		$this->assertTrue( $repo->startNotificationAttempt( $different, $now + 1, true ) );
		$differentState = $repo->findById( $different->id, true )->getRawData();
		$this->assertSame( 0, $repo->recordNotifyDispatched( $oldOperation, 204, $now + 600 ) );
		$this->assertSame( $differentState, $repo->findById( $different->id, true )->getRawData() );
	}

	public function test_superseded_notification_result_continues_to_later_work() :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$first = $repo->upsertActive( 'https://superseded-continue-first.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$second = $repo->upsertActive( 'https://superseded-continue-second.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$sender = new ImportExportPingSenderTestDouble(
			true,
			204,
			'',
			static function ( int $count ) use ( $repo, $first ) :void {
				if ( $count === 1 ) {
					$repo->recordExportSuccess( $repo->findById( $first->id, true ), SitesDB::EXPORT_RESULT_SUCCESS );
				}
			}
		);

		( new ImportExportQueueProcessorTestDouble( $sender, null, $repo ) )->runFromCron();

		$this->assertSame( [ $first->url, $second->url ], $sender->urls );
		$this->assertSame( SitesDB::QUEUE_IDLE, $repo->findById( $first->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_WAITING_EXPORT, $repo->findById( $second->id, true )->queue_status );
	}

	#[DataProvider( 'supersededMaintenanceProvider' )]
	public function test_superseded_timeout_or_exhaustion_continues_to_later_work( string $transition, string $transitionFieldSql ) :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$target = $repo->upsertActive( "https://superseded-{$transition}.example.com", SitesDB::SOURCE_MANUAL, '', true );
		if ( $transition === 'timeout' ) {
			$this->assertTrue( $repo->startNotificationAttempt( $target, $now ) );
			$this->assertSame( 1, $repo->recordNotifyDispatched( $target, 204, $now - 1 ) );
		}
		else {
			$this->startExhaustedNotification( $repo, $target, $now );
		}
		$later = $repo->upsertActive( "https://after-superseded-{$transition}.example.com", SitesDB::SOURCE_MANUAL, '', true );
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$interleaved = false;
		$filter = static function ( string $query ) use ( $repo, $target, $table, $transitionFieldSql, &$interleaved ) :string {
			if ( !$interleaved
				 && \strpos( $query, "UPDATE `{$table}`" ) !== false
				 && \strpos( $query, $transitionFieldSql ) !== false
				 && \strpos( $query, "WHERE `id`={$target->id} AND" ) !== false ) {
				$interleaved = true;
				$repo->recordExportSuccess( $repo->findById( $target->id, true ), SitesDB::EXPORT_RESULT_SUCCESS );
			}
			return $query;
		};
		\add_filter( 'query', $filter, 1000 );
		$sender = new ImportExportPingSenderTestDouble( true, 204, '' );
		try {
			( new ImportExportQueueProcessorTestDouble( $sender, null, $repo ) )->runFromCron();
		}
		finally {
			\remove_filter( 'query', $filter, 1000 );
		}

		$this->assertTrue( $interleaved );
		$this->assertContains( $later->url, $sender->urls );
		$this->assertSame( SitesDB::QUEUE_IDLE, $repo->findById( $target->id, true )->queue_status );
		$this->assertSame( 0, $repo->findById( $target->id, true )->consecutive_failures );
	}

	public static function supersededMaintenanceProvider() :array {
		return [
			'timeout' => [ 'timeout', '`last_export_result_code`=' ],
			'exhaustion' => [ 'exhaustion', '`last_ping_failure_at`=' ],
		];
	}

	public function test_queue_processor_passes_stored_import_id_to_notify_sender() :void {
		$repo = $this->repo();
		$repo->upsertActive( 'https://notify-import-id.example.com', SitesDB::SOURCE_MANUAL, 'stored-import-id', true );
		$sender = new ImportExportPingSenderTestDouble( true, 200, '' );

		( new ImportExportQueueProcessorTestDouble( $sender ) )->runFromCron();

		$this->assertSame( [ 'stored-import-id' ], $sender->importIDs );
		$this->assertSame( [ 5 ], $sender->timeouts );
	}

	public function test_missing_export_request_after_ping_records_export_timeout() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://timeout.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $row, Services::Request()->ts() ) );
		$this->assertSame( 1, $repo->recordNotifyDispatched( $row, 200, Services::Request()->ts() - 1 ) );

		( new ImportExportQueueProcessorTestDouble( new ImportExportPingSenderTestDouble( true, 200, '' ) ) )->runFromCron();

		$row = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $row->queue_status );
		$this->assertGreaterThan( 0, $row->last_export_failure_at );
		$this->assertSame( SitesDB::EXPORT_RESULT_TIMEOUT, $row->last_export_result_code );
		$this->assertSame( 'export_not_requested_before_grace_window', $row->last_export_error );
	}

	public function test_waiting_export_expiry_rule_matches_selection_timeout_repair_cooldown_and_table_filter() :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$cases = [
			'absent' => [ 0, $now - 10, 0, false ],
			'before' => [ $now + 1, $now - 10, 0, false ],
			'at' => [ $now, $now - 10, 0, true ],
			'tie' => [ $now - 1, $now - 10, $now - 10, false ],
			'zeros' => [ $now - 1, 0, 0, true ],
			'older' => [ $now - 1, $now - 10, $now - 11, true ],
			'newer' => [ $now - 1, $now - 10, $now - 9, false ],
		];
		$ids = [];
		$snapshots = [];
		foreach ( $cases as $key => [ $deadline, $pingSuccess, $exportSuccess, $expired ] ) {
			$row = $repo->upsertActive( "https://expiry-matrix-{$key}.example.com", SitesDB::SOURCE_MANUAL, '', true );
			$repo->recordExportServed( $row );
			$row = $repo->findById( $row->id, true );
			$row = $this->setWaitingExportState( $row, [
				'expected_export_by'     => $deadline,
				'last_ping_success_at'   => $pingSuccess,
				'last_export_success_at' => $exportSuccess,
			] );
			$ids[ $key ] = $row->id;
			$qualifyingSuccess = $exportSuccess > 0 && $exportSuccess >= $pingSuccess;
			$this->assertSame( !( $deadline > $now && !$qualifyingSuccess ), $repo->exportCooldownActive( $row, \DAY_IN_SECONDS ), $key.' cooldown' );
			$this->assertSame( $expired ? 1 : 0, $repo->repairConnectionsByIds( [ $row->id ] ), $key.' repair' );
			if ( $expired ) {
				$row = $this->setWaitingExportState( $repo->findById( $row->id, true ), [
					'expected_export_by'     => $deadline,
					'last_ping_success_at'   => $pingSuccess,
					'last_export_success_at' => $exportSuccess,
				] );
			}
			$snapshots[ $key ] = $row;
		}

		$maintenanceIds = \array_column( $repo->selectExportMaintenanceRows( 20 ), 'id' );
		foreach ( [ 'at', 'zeros', 'older' ] as $key ) {
			$this->assertContains( $ids[ $key ], $maintenanceIds );
		}
		$this->assertNotContains( $ids[ 'before' ], $maintenanceIds );
		$this->assertNotContains( $ids[ 'absent' ], $maintenanceIds );

		$table = $this->retrieveImportExportSitesTableData( 'expiry-matrix-', [
			'sync_state' => [ SiteSyncStatusBuilder::STATE_PROBLEM ],
		] );
		$tableIds = \array_column( $table[ 'data' ], 'rid' );
		\sort( $tableIds );
		$expectedProblemIds = [ $ids[ 'at' ], $ids[ 'zeros' ], $ids[ 'older' ] ];
		\sort( $expectedProblemIds );
		$this->assertSame( 3, (int)$table[ 'recordsFiltered' ] );
		$this->assertSame( $expectedProblemIds, $tableIds );

		foreach ( $cases as $key => [ , , , $expired ] ) {
			$this->assertSame( $expired ? 1 : 0, $repo->recordExportTimeout( $snapshots[ $key ] ), $key.' timeout write' );
			$persisted = $repo->findById( $ids[ $key ], true );
			$this->assertSame( $expired ? SitesDB::QUEUE_QUEUED : SitesDB::QUEUE_WAITING_EXPORT, $persisted->queue_status, $key.' timeout state' );
			$this->assertSame( $expired ? 1 : 0, $persisted->consecutive_failures, $key.' timeout failures' );
		}
	}

	public function test_qualifying_wait_is_reconciled_without_rewriting_success_failure_or_identity() :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://reconcile-wait.example.com', SitesDB::SOURCE_MANUAL, 'import-id', true );
		$meta = $row->meta;
		$meta[ 'preserve_this' ] = 'preserved';
		$row = $this->setWaitingExportState( $row, [
			'expected_export_by'       => $now - 1,
			'last_ping_success_at'     => $now - 30,
			'last_export_success_at'   => $now - 30,
			'last_export_result_code'  => SitesDB::EXPORT_RESULT_SUCCESS,
			'last_ping_failure_at'     => $now - 200,
			'last_export_failure_at'   => $now - 100,
			'last_ping_error'          => 'old ping failure',
			'last_export_error'        => 'old export failure',
			'consecutive_failures'     => 4,
			'profile_ref'              => $row->profile_ref,
			'meta'                     => $this->requireController()->db_con->import_export_sites->getRecord()->arrayDataWrap( $meta ) ?? '',
		] );

		$this->assertTrue( $repo->hasActionableWork() );
		( new ImportExportQueueProcessorTestDouble( new ImportExportPingSenderTestDouble( true, 204, '' ), null, $repo ) )->runFromCron();

		$persisted = $repo->findById( $row->id, true );
		$this->assertSame( SitesDB::QUEUE_IDLE, $persisted->queue_status );
		$this->assertSame( $now - 30, $persisted->last_export_success_at );
		$this->assertSame( $now - 30 + \DAY_IN_SECONDS, $persisted->next_ping_at );
		$this->assertSame( SitesDB::EXPORT_RESULT_SUCCESS, $persisted->last_export_result_code );
		$this->assertSame( $now - 200, $persisted->last_ping_failure_at );
		$this->assertSame( $now - 100, $persisted->last_export_failure_at );
		$this->assertSame( 'old ping failure', $persisted->last_ping_error );
		$this->assertSame( 'old export failure', $persisted->last_export_error );
		$this->assertSame( 4, $persisted->consecutive_failures );
		$this->assertSame( 'import-id', $persisted->import_id );
		$this->assertSame( 'preserved', $persisted->meta[ 'preserve_this' ] ?? null );
		$this->assertSame( 0, $persisted->expected_export_by );
		$this->assertSame( 0, $persisted->picked_at );
		$this->assertSame( 0, $persisted->lock_until );
		$this->assertSame( SiteSyncStatusBuilder::STATE_PROBLEM, ( new SiteSyncStatusBuilder( $now ) )->stateForRecord( $persisted ) );
	}

	public function test_reconciliation_is_guarded_and_shares_five_row_maintenance_allowance() :void {
		$now = 1712620800;
		$this->setRequestTimestamp( $now );
		$repo = $this->repo();
		$expiredIds = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$row = $repo->upsertActive( "https://maintenance-expired-{$i}.example.com", SitesDB::SOURCE_MANUAL, '', true );
			$row = $this->setWaitingExportState( $row, [
				'expected_export_by' => $now - 30 + $i,
				'last_ping_success_at' => $now - 60,
			] );
			$expiredIds[] = $row->id;
		}
		$reconcileIds = [];
		for ( $i = 0; $i < 4; $i++ ) {
			$row = $repo->upsertActive( "https://maintenance-reconcile-{$i}.example.com", SitesDB::SOURCE_MANUAL, '', true );
			$row = $this->setWaitingExportState( $row, [
				'expected_export_by' => $now + $i,
				'last_ping_success_at' => $now - 60,
				'last_export_success_at' => $now - 60,
			] );
			$reconcileIds[] = $row->id;
		}

		$selected = $repo->selectExportMaintenanceRows( 5 );
		$this->assertCount( 5, $selected );
		$this->assertSame( $expiredIds, \array_slice( \array_column( $selected, 'id' ), 0, 3 ) );
		$this->assertSame( \array_slice( $reconcileIds, 0, 2 ), \array_slice( \array_column( $selected, 'id' ), 3 ) );

		$guarded = $selected[ 3 ];
		$repo->recordExportFailure( $guarded->url, SitesDB::EXPORT_RESULT_VERIFY_FAILED, 'newer failure' );
		$this->assertSame( 0, $repo->recordExportReconciliation( $guarded ) );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $guarded->id, true )->queue_status );

		$processor = new ImportExportQueueProcessorTestDouble( new ImportExportPingSenderTestDouble( true, 204, '' ), null, $repo );
		$processor->runFromCron();
		$waiting = \array_filter( $repo->selectActiveRows(), static fn( Record $row ) :bool => $row->queue_status === SitesDB::QUEUE_WAITING_EXPORT );
		$this->assertCount( 1, $waiting );
		$this->assertSame( 1, $processor->dispatches );
	}

	public function test_export_failure_updates_export_fields_distinct_from_ping_fields() :void {
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://export-fail.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $row, Services::Request()->ts() ) );
		$this->assertSame( 1, $repo->recordNotifyDispatched( $row, 202, Services::Request()->ts() + 600 ) );

		$repo->recordExportFailure( 'https://export-fail.example.com', SitesDB::EXPORT_RESULT_VERIFY_FAILED, 'verify failed' );

		$row = $repo->findById( $row->id, true );
		$this->assertSame( 202, $row->last_ping_http_code );
		$this->assertGreaterThan( 0, $row->last_ping_success_at );
		$this->assertGreaterThan( 0, $row->last_export_failure_at );
		$this->assertSame( SitesDB::EXPORT_RESULT_VERIFY_FAILED, $row->last_export_result_code );
		$this->assertSame( 'verify failed', $row->last_export_error );
	}

	public function test_export_endpoint_records_successful_slave_download_as_sync_success() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$con = $this->requireController();
		$url = 'https://export-success.example.com';
		$importID = 'export-success-id';
		$con->opts
			->optSet( 'importexport_enable', 'Y' )
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
		$repo->recordExportSuccess( $first, SitesDB::EXPORT_RESULT_SUCCESS );
		$repo->recordExportSuccess( $second, SitesDB::EXPORT_RESULT_SUCCESS );
		$this->assertSame( SitesDB::QUEUE_IDLE, $repo->findById( $first->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_IDLE, $repo->findById( $second->id, true )->queue_status );

		$action = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_QUEUE_SYNC,
			'rids'       => [ $second->id ],
		] );
		$this->execTableAction( $action );

		$first = $repo->findById( $first->id, true );
		$second = $repo->findById( $second->id, true );
		$payload = $action->response()->payload();

		$this->assertSame( SitesDB::QUEUE_IDLE, $first->queue_status );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $second->queue_status );
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_manual_queue_pending_rows_only_returns_connection_guidance() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		$repo = $this->repo();
		$pendingInvite = $repo->upsertPendingClientSite( 'https://manual-pending-invite.example.com', SitesDB::SOURCE_MANUAL, true );
		$pendingConnection = $repo->upsertPendingClientSite( 'https://manual-pending-connection.example.com', SitesDB::SOURCE_MANUAL, false );

		$action = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_QUEUE_SYNC,
			'rids'       => [ $pendingInvite->id, $pendingConnection->id ],
		] );
		$this->execTableAction( $action );

		$payload = $action->response()->payload();
		$ordinaryAction = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_QUEUE_SYNC,
			'rids'       => [ 9999999 ],
		] );
		$this->execTableAction( $ordinaryAction );
		$ordinaryPayload = $ordinaryAction->response()->payload();

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertNotSame( '', $payload[ 'message' ] );
		$this->assertNotSame( $ordinaryPayload[ 'message' ], $payload[ 'message' ] );
		$this->assertSame( SitesDB::QUEUE_PENDING_INVITE, $repo->findById( $pendingInvite->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $repo->findById( $pendingConnection->id, true )->queue_status );
	}

	public function test_manual_queue_invalid_or_deleted_zero_result_keeps_ordinary_message() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		$row = $this->repo()->upsertActive( 'https://manual-queue-deleted.example.com', SitesDB::SOURCE_MANUAL );
		$this->repo()->softDeleteUrl( $row->url );

		$action = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_QUEUE_SYNC,
			'rids'       => [ $row->id, 9999999 ],
		] );
		$this->execTableAction( $action );

		$payload = $action->response()->payload();
		$emptyAction = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_QUEUE_SYNC,
			'rids'       => [],
		] );
		$this->execTableAction( $emptyAction );
		$emptyPayload = $emptyAction->response()->payload();

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( $emptyPayload[ 'message' ], $payload[ 'message' ] );
	}

	public function test_manual_queue_mixed_pending_and_eligible_rows_keeps_success_message() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		$repo = $this->repo();
		$pendingInvite = $repo->upsertPendingClientSite( 'https://manual-queue-pending-invite.example.com', SitesDB::SOURCE_MANUAL, true );
		$pendingConnection = $repo->upsertPendingClientSite( 'https://manual-queue-pending-connection.example.com', SitesDB::SOURCE_MANUAL, false );
		$eligible = $repo->upsertActive( 'https://manual-queue-eligible.example.com', SitesDB::SOURCE_MANUAL );
		$control = $repo->upsertActive( 'https://manual-queue-control.example.com', SitesDB::SOURCE_MANUAL );
		$repo->recordExportSuccess( $eligible, SitesDB::EXPORT_RESULT_SUCCESS );
		$repo->recordExportSuccess( $control, SitesDB::EXPORT_RESULT_SUCCESS );

		$action = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_QUEUE_SYNC,
			'rids'       => [ $pendingInvite->id, $pendingConnection->id, $eligible->id ],
		] );
		$this->execTableAction( $action );

		$payload = $action->response()->payload();
		$controlAction = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_QUEUE_SYNC,
			'rids'       => [ $control->id ],
		] );
		$this->execTableAction( $controlAction );
		$controlPayload = $controlAction->response()->payload();

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( $controlPayload[ 'message' ], $payload[ 'message' ] );
		$this->assertSame( SitesDB::QUEUE_PENDING_INVITE, $repo->findById( $pendingInvite->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $repo->findById( $pendingConnection->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $eligible->id )->queue_status );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $control->id )->queue_status );
	}

	public function test_manual_repair_action_clears_stale_connection_state_and_queues_selected_site() :void {
		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], 1712620800 ),
		] );
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );
		$repo = $this->repo();
		$workingUrl = 'https://repair-keep.example.com';
		$brokenUrl = 'https://repair-broken.example.com';
		$working = $repo->upsertActive( $workingUrl, SitesDB::SOURCE_MANUAL, 'working-id', true );
		$broken = $repo->upsertActive( $brokenUrl, SitesDB::SOURCE_MANUAL, 'stale-id', true );
		$repo->recordExportSuccess( $working, SitesDB::EXPORT_RESULT_SUCCESS, 'working-id' );
		$repo->recordExportSuccess( $broken, SitesDB::EXPORT_RESULT_SUCCESS, 'stale-id' );
		$broken = $this->requireSite( $brokenUrl, true );
		$repo->recordExportServed( $broken );
		$repo->recordHandshakeAttempt( $broken );
		$repo->recordExportFailure( $broken->url, SitesDB::EXPORT_RESULT_VERIFY_FAILED, 'verify failed' );
		$broken = $this->requireSite( $brokenUrl, true );
		$this->assertSame( SiteSyncStatusBuilder::STATE_PROBLEM, ( new SiteSyncStatusBuilder( Services::Request()->ts() ) )->stateForRecord( $broken ) );
		$this->assertTrue( $repo->exportCooldownActive( $broken, \DAY_IN_SECONDS ) );
		$this->assertTrue( $repo->handshakeCooldownActive( $broken, \DAY_IN_SECONDS ) );

		$action = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_REPAIR_CONNECTION,
			'rids'       => [ $broken->id ],
		] );
		$this->execTableAction( $action );

		$working = $this->requireSite( $workingUrl, true );
		$broken = $this->requireSite( $brokenUrl, true );
		$payload = $action->response()->payload();

		$this->assertSame( 'working-id', $working->import_id );
		$this->assertSame( SitesDB::QUEUE_IDLE, $working->queue_status );
		$this->assertSame( '', $broken->import_id );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $broken->queue_status );
		$this->assertSame( Services::Request()->ts(), $broken->next_ping_at );
		$this->assertSame( 0, $broken->consecutive_failures );
		$this->assertSame( 0, $broken->last_ping_failure_at );
		$this->assertSame( 0, $broken->last_export_failure_at );
		$this->assertSame( '', $broken->last_ping_error );
		$this->assertSame( '', $broken->last_export_error );
		$this->assertSame( '', $broken->last_export_result_code );
		$this->assertFalse( $repo->exportCooldownActive( $broken, \DAY_IN_SECONDS ) );
		$this->assertFalse( $repo->handshakeCooldownActive( $broken, \DAY_IN_SECONDS ) );
		$this->assertSame( SiteSyncStatusBuilder::STATE_PENDING, ( new SiteSyncStatusBuilder( Services::Request()->ts() ) )->stateForRecord( $broken ) );
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );

		$repo->recordExportSuccess( $broken, SitesDB::EXPORT_RESULT_SUCCESS, 'fresh-id' );

		$this->assertSame( 'fresh-id', $this->requireSite( $brokenUrl, true )->import_id );
	}

	public function test_manual_queue_retry_prevents_stale_repair_until_another_failure() :void {
		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], 1712620800 ),
		] );
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://repair-after-manual-retry.example.com', SitesDB::SOURCE_MANUAL, 'existing-id', true );
		$statusBuilder = new SiteSyncStatusBuilder( Services::Request()->ts() );
		$repo->recordExportFailure( $row->url, SitesDB::EXPORT_RESULT_VERIFY_FAILED, 'verify failed' );
		$this->assertSame( SiteSyncStatusBuilder::STATE_PROBLEM, $statusBuilder->stateForRecord( $this->requireSite( $row->url, true ) ) );
		$this->assertSame( 1, $repo->queueSiteIds( [ $row->id ] ) );
		$queued = $this->requireSite( $row->url, true );
		$this->assertSame( SiteSyncStatusBuilder::STATE_PENDING, $statusBuilder->stateForRecord( $queued ) );
		$before = $queued->getRawData();

		$this->execTableAction( new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_REPAIR_CONNECTION,
			'rids'       => [ $row->id ],
		] ) );

		$this->assertSame( $before, $this->requireSite( $row->url, true )->getRawData() );
		$repo->recordExportFailure( $row->url, SitesDB::EXPORT_RESULT_VERIFY_FAILED, 'retry failed' );
		$this->assertSame( SiteSyncStatusBuilder::STATE_PROBLEM, $statusBuilder->stateForRecord( $this->requireSite( $row->url, true ) ) );
		$this->assertSame( 1, $repo->repairConnectionsByIds( [ $row->id ] ) );
		$this->assertSame( '', $this->requireSite( $row->url, true )->import_id );
	}

	public function test_manual_repair_action_ignores_non_problem_deleted_and_missing_rows() :void {
		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], 1712620800 ),
		] );
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );
		$repo = $this->repo();
		$statusBuilder = new SiteSyncStatusBuilder( Services::Request()->ts() );
		$working = $repo->upsertActive( 'https://repair-healthy.example.com', SitesDB::SOURCE_MANUAL, 'healthy-id', true );
		$neverSynced = $repo->upsertActive( 'https://repair-never-synced.example.com', SitesDB::SOURCE_MANUAL, 'never-id' );
		$pending = $repo->upsertActive( 'https://repair-pending.example.com', SitesDB::SOURCE_MANUAL, 'pending-id', true );
		$deleted = $repo->upsertActive( 'https://repair-deleted.example.com', SitesDB::SOURCE_MANUAL, 'deleted-id', true );
		$broken = $repo->upsertActive( 'https://repair-only-broken.example.com', SitesDB::SOURCE_MANUAL, 'stale-id', true );
		$repo->recordExportSuccess( $working, SitesDB::EXPORT_RESULT_SUCCESS, 'healthy-id' );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $neverSynced->id, [
			'queue_status' => SitesDB::QUEUE_IDLE,
			'queued_at'    => 0,
			'next_ping_at' => 0,
		] );
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $pending->id, [
			'queue_status' => SitesDB::QUEUE_PENDING_CONNECTION,
			'next_ping_at' => 0,
		] );
		$repo->softDeleteUrl( $deleted->url );
		$repo->recordExportSuccess( $broken, SitesDB::EXPORT_RESULT_SUCCESS, 'stale-id' );
		$repo->recordExportFailure( $broken->url, SitesDB::EXPORT_RESULT_VERIFY_FAILED, 'verify failed' );
		$working = $this->requireSite( $working->url, true );
		$neverSynced = $this->requireSite( $neverSynced->url, true );
		$pending = $this->requireSite( $pending->url, true );
		$deleted = $this->requireSite( $deleted->url, true );
		$broken = $this->requireSite( $broken->url, true );
		$this->assertSame( SiteSyncStatusBuilder::STATE_WORKING, $statusBuilder->stateForRecord( $working ) );
		$this->assertSame( SiteSyncStatusBuilder::STATE_NEVER_SYNCED, $statusBuilder->stateForRecord( $neverSynced ) );
		$this->assertSame( SiteSyncStatusBuilder::STATE_PENDING, $statusBuilder->stateForRecord( $pending ) );
		$this->assertSame( SiteSyncStatusBuilder::STATE_INACTIVE, $statusBuilder->stateForRecord( $deleted ) );
		$this->assertSame( SiteSyncStatusBuilder::STATE_PROBLEM, $statusBuilder->stateForRecord( $broken ) );

		$action = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_REPAIR_CONNECTION,
			'rids'       => [
				$working->id,
				$neverSynced->id,
				$pending->id,
				$deleted->id,
				$broken->id,
				$broken->id + 10000,
			],
		] );
		$this->execTableAction( $action );

		$working = $this->requireSite( $working->url, true );
		$neverSynced = $this->requireSite( $neverSynced->url, true );
		$pending = $this->requireSite( $pending->url, true );
		$deleted = $this->requireSite( $deleted->url, true );
		$broken = $this->requireSite( $broken->url, true );
		$payload = $action->response()->payload();

		$this->assertSame( 'healthy-id', $working->import_id );
		$this->assertSame( SitesDB::QUEUE_IDLE, $working->queue_status );
		$this->assertSame( 'never-id', $neverSynced->import_id );
		$this->assertSame( SitesDB::QUEUE_IDLE, $neverSynced->queue_status );
		$this->assertSame( 'pending-id', $pending->import_id );
		$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $pending->queue_status );
		$this->assertSame( 'deleted-id', $deleted->import_id );
		$this->assertSame( SitesDB::STATUS_DELETED, $deleted->status );
		$this->assertSame( '', $broken->import_id );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $broken->queue_status );
		$this->assertSame( Services::Request()->ts(), $broken->next_ping_at );
		$this->assertSame( 0, $broken->consecutive_failures );
		$this->assertArrayHasKey( 'success', $payload );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_manual_delete_action_hard_deletes_only_selected_sites() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		$repo = $this->repo();
		$first = $repo->upsertActive( 'https://delete-keep.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$second = $repo->upsertPendingClientSite( 'https://delete-remove-one.example.com', SitesDB::SOURCE_MANUAL, false );
		$third = $repo->upsertActive( 'https://delete-remove-two.example.com', SitesDB::SOURCE_MANUAL, '', true );

		$action = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_DELETE_SITE,
			'rids'       => [ $second->id, $third->id ],
		] );
		$this->execTableAction( $action );

		$payload = $action->response()->payload();

		$this->assertInstanceOf( Record::class, $repo->findById( $first->id, true ) );
		$this->assertNull( $repo->findById( $second->id, true ) );
		$this->assertNull( $repo->findById( $third->id, true ) );
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
		$this->execTableAction( $action );

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
		$repo->recordExportSuccess( $row, SitesDB::EXPORT_RESULT_SUCCESS );

		$action = new ImportExportSitesTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_QUEUE_SYNC,
			'rids'       => [ $row->id ],
		] );
		$this->execTableAction( $action );

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
		$pendingInvite = $repo->upsertPendingClientSite( 'https://all-active-pending-invite.example.com', SitesDB::SOURCE_MANUAL, true );
		$pendingConnection = $repo->upsertPendingClientSite( 'https://all-active-pending-connection.example.com', SitesDB::SOURCE_MANUAL, false );
		$repo->recordExportSuccess( $first, SitesDB::EXPORT_RESULT_SUCCESS );
		$repo->recordExportSuccess( $second, SitesDB::EXPORT_RESULT_SUCCESS );

		$count = ( new ImportExportController() )->queueAllActiveSitesForSync();

		$this->assertSame( 2, $count );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $first->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $second->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_PENDING_INVITE, $repo->findById( $pendingInvite->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $repo->findById( $pendingConnection->id, true )->queue_status );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_controller_rejects_queue_all_active_when_disabled_without_scheduling() :void {
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://all-active-disabled.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordExportSuccess( $row, SitesDB::EXPORT_RESULT_SUCCESS );

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

	public function test_queue_scheduler_worker_returns_to_later_hook_callbacks() :void {
		$events = [];
		$scheduler = new QueueScheduler(
			static fn() :bool => true,
			static function () use ( &$events ) :void {
				$events[] = 'worker';
			}
		);
		$hook = $scheduler->hook();
		\remove_all_actions( $hook );
		\wp_clear_scheduled_hook( $hook );

		try {
			$scheduler->setup();
			\add_action( $hook, static function () use ( &$events ) :void {
				$events[] = 'later callback';
			}, 20 );

			\do_action( $hook );

			$this->assertSame( [ 'worker', 'later callback' ], $events );
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

	/** @group database-transaction-exception */
	public function test_add_only_schema_alignment_preserves_populated_rows_and_extra_columns() :void {
		$url = 'https://schema.example.com';
		$this->assertNull( $this->repo()->findByUrl( $url, true ) );
		$profileBefore = ( new ProfileRepository() )->findBySlug( ProfileRepository::DEFAULT_SLUG );
		$readyCacheSnapshot = Services::WpGeneral()->getOption( TableReadyCache::DB_STATUS_KEY );
		$rowID = 0;
		$createdProfileID = 0;
		$table = '';

		$this->runWithPersistentDatabaseMutation(
			function () use ( $url, $profileBefore, &$rowID, &$createdProfileID, &$table ) :void {
				$this->requireDb( ProfilesDB::DB_KEY );
				$this->requireDb( SitesDB::DB_KEY );
				$profile = ( new ProfileRepository() )->ensureDefaultProfile();
				$this->assertNotEmpty( $profile );
				if ( $profileBefore === null ) {
					$createdProfileID = $profile->id;
				}

				$repo = $this->repo();
				$row = $repo->upsertActive( $url, SitesDB::SOURCE_MANUAL, 'schema-id', true );
				$this->assertInstanceOf( Record::class, $row );
				$rowID = $row->id;
				$handler = $this->requireController()->db_con->import_export_sites;
				$table = $handler->getTable();

				global $wpdb;
				$this->assertNotFalse( $wpdb->query(
					"ALTER TABLE `{$table}` ADD COLUMN `extra_probe` varchar(32) NOT NULL DEFAULT ''"
				) );
				Services::WpDb()->clearResultShowTables();
				$this->requireController()->db_con->loadDbH(
					$this->requireController()->db_con::MAP[ SitesDB::DB_KEY ][ 'slug' ],
					true
				);

				$this->assertSame( 'schema-id', $repo->findById( $row->id, true )->import_id );
				$this->assertContains( 'extra_probe', Services::WpDb()->getColumnsForTable( $table ) );
				$this->assertTrue( $this->requireController()->db_con->import_export_sites->isReady() );
			},
			function () use ( &$rowID, &$createdProfileID, &$table, $readyCacheSnapshot ) :void {
				global $wpdb;
				if ( $table !== '' && \in_array( 'extra_probe', Services::WpDb()->getColumnsForTable( $table ), true ) ) {
					$result = $wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `extra_probe`" );
					if ( $result === false ) {
						throw new \RuntimeException( 'Failed to remove the import/export schema probe column.' );
					}
					Services::WpDb()->clearResultShowTables();
				}

				$this->requireController()->db_con->reset();
				$this->requireDb( ProfilesDB::DB_KEY );
				$this->requireDb( SitesDB::DB_KEY );
				if ( $rowID > 0 ) {
					$repo = $this->repo();
					$repo->deleteByIds( [ $rowID ] );
					if ( $repo->findById( $rowID, true ) instanceof Record ) {
						throw new \RuntimeException( 'Failed to remove the import/export schema probe row.' );
					}
				}
				if ( $createdProfileID > 0 ) {
					$profiles = $this->requireController()->db_con->import_export_profiles;
					$profiles
						->getQueryDeleter()
						->deleteById( $createdProfileID );
					if ( ( new ProfileRepository() )->findById( $createdProfileID ) !== null ) {
						throw new \RuntimeException( 'Failed to remove the import/export schema probe profile.' );
					}
				}

				RuntimeTestState::restoreTableReadyCache( $readyCacheSnapshot );
				$this->persistentStateRestored = true;
			}
		);
	}

	public function test_table_url_ordering_ignores_www() :void {
		$repo = $this->repo();
		$repo->upsertActive( 'https://url-order-ignore-www-bravo.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->upsertActive( 'https://www.url-order-ignore-www-alpha.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->upsertActive( 'https://www.url-order-ignore-www-charlie.example.com', SitesDB::SOURCE_MANUAL, '', true );

		$table = $this->retrieveImportExportSitesTableData( 'url-order-ignore-www', [] );

		$this->assertSame( 3, (int)$table[ 'recordsFiltered' ] );
		$this->assertSame( [
			'https://www.url-order-ignore-www-alpha.example.com',
			'https://url-order-ignore-www-bravo.example.com',
			'https://www.url-order-ignore-www-charlie.example.com',
		], \array_column( $table[ 'data' ], 'url' ) );
	}

	public function test_table_url_search_ignores_www_and_excludes_non_url_fields() :void {
		$repo = $this->repo();
		$withWww = $repo->upsertActive( 'https://www.url-search-ignore-www-alpha.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$withoutWww = $repo->upsertActive( 'https://url-search-ignore-www-beta.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$errorOnly = $repo->upsertActive( 'https://url-search-ignore-www-hidden.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $errorOnly, Services::Request()->ts() ) );
		$this->assertSame( 1, $repo->recordPingFailure( $errorOnly, 503, 'url-search-hidden-token' ) );

		$withoutPrefixSearch = $this->retrieveImportExportSitesTableData( 'url-search-ignore-www-alpha.example.com', [] );
		$this->assertSame( [ $withWww->id ], \array_column( $withoutPrefixSearch[ 'data' ], 'rid' ) );

		$withPrefixSearch = $this->retrieveImportExportSitesTableData( 'www.url-search-ignore-www-beta.example.com', [] );
		$this->assertSame( [ $withoutWww->id ], \array_column( $withPrefixSearch[ 'data' ], 'rid' ) );

		$nonUrlSearch = $this->retrieveImportExportSitesTableData( 'url-search-hidden-token', [] );
		$this->assertSame( 0, (int)$nonUrlSearch[ 'recordsFiltered' ] );
		$this->assertSame( [], $nonUrlSearch[ 'data' ] );
	}

	public function test_table_data_repairs_orphaned_profile_ref_before_rendering_profile_label() :void {
		$profile = ( new ProfileRepository() )->ensureDefaultProfile();
		$this->assertNotEmpty( $profile );
		$repo = $this->repo();
		$row = $repo->upsertActive( 'https://table-profile-repair.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$orphanProfileRef = $profile->id + 10000;
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, [
			'profile_ref' => $orphanProfileRef,
		] );
		$this->assertSame( $orphanProfileRef, $repo->findById( $row->id, true )->profile_ref );

		$table = $this->retrieveImportExportSitesTableData( 'table-profile-repair', [] );

		$this->assertSame( 1, (int)$table[ 'recordsFiltered' ] );
		$this->assertSame( $row->id, $table[ 'data' ][ 0 ][ 'rid' ] );
		$this->assertSame( ProfileRepository::DEFAULT_LABEL, $table[ 'data' ][ 0 ][ 'profile' ] );
		$this->assertSame( $profile->id, $repo->findById( $row->id, true )->profile_ref );
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

	private function isExportSuccessUpdate( string $query, string $table ) :bool {
		$successColumn = \strpos( $query, '`last_export_success_at`=' );
		$where = \strpos( $query, ' WHERE ' );
		return \strpos( $query, "UPDATE `{$table}` SET " ) === 0
			   && $successColumn !== false
			   && $where !== false
			   && $successColumn < $where;
	}

	private function startExhaustedNotification( SiteRepository $repo, Record $row, int $now ) :void {
		$this->assertTrue( $repo->startNotificationAttempt( $row, $now - 180 ) );
		$this->assertTrue( $repo->startNotificationAttempt( $row, $now - 120, true ) );
		$this->assertTrue( $repo->startNotificationAttempt( $row, $now - 60, true ) );
	}

	private function prepareTimeoutOrExhaustionCandidate(
		SiteRepository $repo,
		string $transition,
		string $url,
		int $now
	) :Record {
		$row = $repo->upsertActive( $url, SitesDB::SOURCE_MANUAL, '', true );
		if ( $transition === 'timeout' ) {
			$this->assertTrue( $repo->startNotificationAttempt( $row, $now ) );
			$this->assertSame( 1, $repo->recordNotifyDispatched( $row, 204, $now - 1 ) );
		}
		else {
			$this->startExhaustedNotification( $repo, $row, $now );
		}
		return $repo->findById( $row->id, true );
	}

	private function setWaitingExportState( Record $row, array $overrides = [] ) :Record {
		$this->requireController()->db_con->import_export_sites->getQueryUpdater()->updateById( $row->id, \array_merge( [
			'queue_status'       => SitesDB::QUEUE_WAITING_EXPORT,
			'expected_export_by' => Services::Request()->ts() + 600,
			'last_ping_success_at' => Services::Request()->ts(),
		], $overrides ) );
		return $this->repo()->findById( $row->id, true );
	}

	private function seedSearchPaneImportExportSites() :array {
		$repo = $this->repo();
		$working = $repo->upsertActive( 'https://sync-pane-filter-working.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$repo->recordExportSuccess( $working, SitesDB::EXPORT_RESULT_SUCCESS );

		$problem = $repo->upsertActive( 'https://sync-pane-filter-problem.example.com', SitesDB::SOURCE_MANUAL, '', true );
		$this->assertTrue( $repo->startNotificationAttempt( $problem, Services::Request()->ts() ) );
		$this->assertSame( 1, $repo->recordPingFailure( $problem, 503, 'service unavailable' ) );

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

	private function runWithFailedImportExportSiteQuery( string $family, callable $callback ) :void {
		global $wpdb;
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$failed = false;
		$filter = function ( $query ) use ( $family, $table, &$failed ) {
			$query = (string)$query;
			if ( !$failed
				 && \stripos( $query, $table ) !== false
				 && $this->classifyImportExportSiteQuery( $query ) === $family ) {
				$failed = true;
				return 'THIS IS INTENTIONALLY INVALID SQL FOR IMPORT EXPORT TESTING';
			}
			return $query;
		};
		$previousSuppressErrors = $wpdb->suppress_errors( true );
		\add_filter( 'query', $filter, \PHP_INT_MAX, 1 );
		try {
			$callback();
		}
		finally {
			\remove_filter( 'query', $filter, \PHP_INT_MAX );
			$wpdb->suppress_errors( $previousSuppressErrors );
		}
		$this->assertTrue( $failed, "Expected {$family} query to be intercepted." );
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

	private function runWithImportExportSitesPersistentMutation( callable $exercise, bool $preserveCronArray = false ) :void {
		$con = $this->requireController();
		$previousVersion = $con->cfg->previous_version;
		$previousRebuilt = $con->cfg->rebuilt;
		$readyCacheSnapshot = Services::WpGeneral()->getOption( TableReadyCache::DB_STATUS_KEY );
		$cronSnapshot = null;

		$this->runWithPersistentDatabaseMutation(
			function () use ( $exercise, $preserveCronArray, &$cronSnapshot ) :void {
				if ( $preserveCronArray ) {
					$cronSnapshot = $this->snapshotCronArray();
				}
				$this->requireDb( ProfilesDB::DB_KEY );
				$this->requireDb( SitesDB::DB_KEY );
				$this->requireController()->opts
					->optSet( SiteRepository::MIGRATED_AT_OPTION, 0 )
					->store();
				$exercise();
			},
			function () use (
				$previousVersion,
				$previousRebuilt,
				$readyCacheSnapshot,
				$preserveCronArray,
				&$cronSnapshot
			) :void {
				$this->recreateCanonicalImportExportSitesTable();
				$this->restoreSelectedOptions( $this->optionsSnapshot );
				$this->restoreStoredConfigOptionSnapshot();

				$con = $this->requireController();
				$con->cfg->previous_version = $previousVersion;
				$con->cfg->rebuilt = $previousRebuilt;
				RuntimeTestState::restoreTableReadyCache( $readyCacheSnapshot );
				if ( $preserveCronArray && \is_array( $cronSnapshot ) ) {
					$this->restoreCronArray( $cronSnapshot );
				}
				else {
					$queueHook = ( new QueueScheduler() )->hook();
					$this->invalidateCronOptionCaches();
					if ( \wp_next_scheduled( $queueHook ) !== false ) {
						throw new \RuntimeException( 'Failed to clear the import/export queue schedule.' );
					}
				}
				$this->persistentStateRestored = true;
			}
		);
	}

	private function recreateCanonicalImportExportSitesTable() :void {
		$handler = $this->newImportExportSitesHandler( false );
		$this->dropImportExportSitesTable( false );
		$handler->execute();
		if ( !$handler->isReady() || !$handler->tableExists() ) {
			throw new \RuntimeException( 'Failed to restore the canonical import/export sites table.' );
		}
		$this->requireController()->db_con->reset();
	}

	private function restoreStoredConfigOptionSnapshot() :void {
		if ( $this->storedConfigOptionSnapshot === false ) {
			Services::WpGeneral()->deleteOption( $this->configStoreKey );
		}
		else {
			Services::WpGeneral()->updateOption( $this->configStoreKey, $this->storedConfigOptionSnapshot );
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
		if ( $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ) === false ) {
			throw new \RuntimeException( 'Failed to drop the import/export sites table: '.$wpdb->last_error );
		}
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

	private function setRequestTimestamp( int $timestamp ) :void {
		ServicesState::mergeItems( [
			'service_request' => new ImportExportSitesExportRequestStub( [], $timestamp ),
		] );
	}

	private function execTableAction( ImportExportSitesTableAction $action ) :void {
		$method = new \ReflectionMethod( $action, 'exec' );
		$method->setAccessible( true );
		$method->invoke( $action );
	}
}

class ImportExportQueueProcessorTestDouble extends QueueProcessor {

	public int $dispatches = 0;
	public $dispatchResult = [];
	public bool $memoryExceeded = false;
	private PingSender $sender;
	private ?SyncSiteInviteSender $inviteSender;
	private ?SiteRepository $siteRepository;
	private $clock;

	public function __construct(
		PingSender $sender,
		?SyncSiteInviteSender $inviteSender = null,
		?SiteRepository $siteRepository = null,
		?callable $clock = null
	) {
		$this->sender = $sender;
		$this->inviteSender = $inviteSender;
		$this->siteRepository = $siteRepository;
		$this->clock = $clock;
		parent::__construct( static fn() :bool => true );
	}

	public function dispatch() {
		$this->dispatches++;
		return $this->dispatchResult;
	}

	protected function now() :float {
		return \is_callable( $this->clock ) ? ( $this->clock )() : parent::now();
	}

	protected function memory_exceeded() {
		return $this->memoryExceeded || parent::memory_exceeded();
	}

	protected function repository() :SiteRepository {
		return $this->siteRepository ?? parent::repository();
	}

	protected function pingSender() :PingSender {
		return $this->sender;
	}

	protected function inviteSender() :SyncSiteInviteSender {
		return $this->inviteSender ?? parent::inviteSender();
	}
}

class ImportExportPingSenderTestDouble extends PingSender {

	public array $urls = [];
	public array $importIDs = [];
	public array $timeouts = [];

	private bool $success;
	private int $httpCode;
	private string $error;
	private $afterSend;
	private $resultProvider;

	public function __construct(
		bool $success,
		int $httpCode,
		string $error,
		?callable $afterSend = null,
		?callable $resultProvider = null
	) {
		$this->success = $success;
		$this->httpCode = $httpCode;
		$this->error = $error;
		$this->afterSend = $afterSend;
		$this->resultProvider = $resultProvider;
	}

	public function send( string $url, int $timeout = 5, string $importID = '' ) :array {
		$this->urls[] = $url;
		$this->importIDs[] = $importID;
		$this->timeouts[] = $timeout;
		if ( \is_callable( $this->afterSend ) ) {
			( $this->afterSend )( \count( $this->urls ), $url );
		}
		if ( \is_callable( $this->resultProvider ) ) {
			$result = ( $this->resultProvider )( $url );
			if ( \is_array( $result ) ) {
				return $result + [ 'observation' => null ];
			}
		}
		return [
			'success'     => $this->success,
			'http_code'   => $this->httpCode,
			'error'       => $this->error,
			'observation' => null,
		];
	}
}

class ImportExportInviteSenderTestDouble extends SyncSiteInviteSender {

	public array $urls = [];
	public array $timeouts = [];
	private array $results;
	private int $httpStatus;
	private $afterSend;

	public function __construct(
		array $results = [ InvitationMetadata::RESULT_HTTP_RESPONSE ],
		int $httpStatus = 200,
		?callable $afterSend = null
	) {
		$this->results = $results;
		$this->httpStatus = $httpStatus;
		$this->afterSend = $afterSend;
	}

	public function send( string $url, int $timeout = 2 ) :array {
		$this->urls[] = $url;
		$this->timeouts[] = $timeout;
		if ( \is_callable( $this->afterSend ) ) {
			( $this->afterSend )( \count( $this->urls ), $url );
		}
		$outcome = \array_shift( $this->results ) ?? InvitationMetadata::RESULT_SENDER_FAILURE;
		return \is_array( $outcome ) ? [
			'result'      => (string)( $outcome[ 0 ] ?? InvitationMetadata::RESULT_SENDER_FAILURE ),
			'http_status' => (int)( $outcome[ 1 ] ?? 0 ),
		] : [
			'result'      => (string)$outcome,
			'http_status' => $this->httpStatus,
		];
	}
}

class ImportExportRestartAfterStartRepositoryTestDouble extends SiteRepository {

	public string $restartCycleID = '';

	public function startInviteAttempt( Record $row, int $startedAt ) {
		$result = parent::startInviteAttempt( $row, $startedAt );
		if ( $result === 1 ) {
			parent::recordInviteResult( $row, InvitationMetadata::RESULT_HTTP_RESPONSE, 200 );
			parent::restartInvitationsByIds( [ $row->id ] );
			$current = parent::findById( $row->id, true );
			$this->restartCycleID = ( new InvitationMetadata() )->normalize( $current->meta )[ 'cycle_id' ];
		}
		return $result;
	}
}

class ImportExportFailedResultRepositoryTestDouble extends SiteRepository {

	public function recordInviteResult( Record $row, string $result, int $httpStatus = 0 ) {
		return false;
	}
}

class ImportExportFailedNotificationWriteRepositoryTestDouble extends SiteRepository {

	public string $failedWrite = '';
	public int $failedRecordID = 0;

	public function startNotificationAttempt( Record $row, int $startedAt, bool $recovery = false ) :bool {
		return $this->shouldFail( __FUNCTION__, $row ) ? false : parent::startNotificationAttempt( $row, $startedAt, $recovery );
	}

	public function recordNotifyDispatched( Record $row, int $httpCode, int $expectedExportBy ) {
		return $this->shouldFail( __FUNCTION__, $row ) ? false : parent::recordNotifyDispatched( $row, $httpCode, $expectedExportBy );
	}

	public function recordInterruptedNotificationExhaustion( Record $row ) {
		return $this->shouldFail( __FUNCTION__, $row ) ? false : parent::recordInterruptedNotificationExhaustion( $row );
	}

	public function recordExportTimeout( Record $row ) {
		return $this->shouldFail( __FUNCTION__, $row ) ? false : parent::recordExportTimeout( $row );
	}

	public function recordExportReconciliation( Record $row ) {
		return $this->shouldFail( __FUNCTION__, $row ) ? false : parent::recordExportReconciliation( $row );
	}

	private function shouldFail( string $write, Record $row ) :bool {
		return $this->failedWrite === $write && $this->failedRecordID === $row->id;
	}
}

class ImportExportCompletedAfterRecoverySelectionRepositoryTestDouble extends SiteRepository {

	public int $completeAfterSelectingID = 0;
	private bool $completed = false;

	public function selectNextInterruptedNotification( ?int $now = null ) :?Record {
		$row = parent::selectNextInterruptedNotification( $now );
		if ( !$this->completed && $row instanceof Record && $row->id === $this->completeAfterSelectingID ) {
			$this->completed = true;
			parent::recordExportSuccess( $row, SitesDB::EXPORT_RESULT_SUCCESS );
		}
		return $row;
	}
}

class ImportExportSelectionClockRepositoryTestDouble extends SiteRepository {

	public float $advanceAfterSelectionTo = 0.0;
	private object $clock;

	public function __construct( object $clock ) {
		$this->clock = $clock;
	}

	public function selectNextDueWork( ?int $now = null ) :?Record {
		$row = parent::selectNextDueWork( $now );
		if ( $row instanceof Record && $this->advanceAfterSelectionTo > 0 ) {
			$this->clock->now = $this->advanceAfterSelectionTo;
		}
		return $row;
	}
}

class ImportExportMaintenanceClockRepositoryTestDouble extends SiteRepository {

	private object $clock;

	public function __construct( object $clock ) {
		$this->clock = $clock;
	}

	public function recordExportTimeout( Record $row ) {
		$result = parent::recordExportTimeout( $row );
		$this->clock->now += QueueProcessor::START_CUTOFF;
		return $result;
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
