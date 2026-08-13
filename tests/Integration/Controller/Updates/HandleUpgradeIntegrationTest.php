<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Controller\Updates;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates\HandleUpgrade;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ScanActionVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class HandleUpgradeIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
	}

	/** @group database-transaction-exception */
	public function testScheduledWorkerWidensScanMetaAndPreservesLargeEligibilityPayloads() :void {
		$con = $this->requireController();
		$schema = $con->db_con->scans->getTableSchema();
		$table = $schema->table;
		$targetDefinition = $schema->enumerateColumns()[ 'meta' ];
		$previousVersion = $con->cfg->previous_version;
		$previousPersistRequired = $con->cfg->persist_required;
		$configStoreKey = 'aptoweb_controller_'.\substr( \hash( 'md5', \get_class( $con ) ), 0, 6 );
		$configStoreSnapshot = Services::WpGeneral()->getOption( $configStoreKey );
		$committedScanIDs = [];
		$hook = $con->prefix( 'plugin-upgrade' );
		global $wpdb;

		$this->runWithPersistentDatabaseMutation(
			function () use ( $con, $table, $hook, $wpdb, &$committedScanIDs ) :void {
				$this->requireDb( 'scans' );
				$this->assertSame( 'mediumtext', $this->scanMetaColumnType( $table ) );
				$this->assertNotFalse( $wpdb->query(
					"ALTER TABLE `{$table}` MODIFY COLUMN `meta` text NOT NULL COMMENT 'Scan Meta Info';"
				) );
				$smallScanID = $this->insertScanWithMeta( [
					'coverage_families' => [ ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY ],
					'fixture'           => 'preserve-me',
				] );
				$committedScanIDs[] = $smallScanID;

				$this->runUpgradeWorker( $hook );
				$this->assertSame( 'mediumtext', $this->scanMetaColumnType( $table ) );
				$smallRecord = $con->db_con->scans->getQuerySelector()->byId( $smallScanID );
				$this->assertSame( 'preserve-me', $smallRecord->meta[ 'fixture' ] ?? null );

				$eligibility = [];
				for ( $i = 0; $i < 1400; $i++ ) {
					$eligibility[ \sprintf( 'large/plugin-%04d.php', $i ) ] = [
						'version'             => \str_repeat( (string)( $i % 10 ), 64 ),
						'comparison_eligible' => $i % 2 === 0,
					];
				}
				$action = ( new ScanActionVO() )->applyFromArray( [
					'scan'       => 'afs',
					'scope_type' => 'full',
					'scope_key'  => '',
					'coverage_families' => [ ScanActionVO::COVERAGE_FAMILY_PLUGIN_INTEGRITY ],
					'asset_snapshot_eligibility' => [
						'plugin' => $eligibility,
						'theme'  => [],
					],
				] );
				$largeRecord = $this->newScanRecordWithMeta( $action->getRawData() );
				$largeRaw = $largeRecord->getRawData();
				$this->assertArrayHasKey( 'meta', $largeRaw );
				$this->assertGreaterThan( 65535, \strlen( (string)$largeRaw[ 'meta' ] ) );
				$this->assertTrue( $con->db_con->scans->getQueryInserter()->insert( $largeRecord ) );
				$largeScanID = (int)$GLOBALS[ 'wpdb' ]->insert_id;
				$committedScanIDs[] = $largeScanID;
				$largeRecord = $con->db_con->scans->getQuerySelector()->byId( $largeScanID );
				$rehydrated = ( new ScanActionVO() )->applyFromArray( $largeRecord->meta );
				$this->assertTrue( $rehydrated->hasValidAssetSnapshotEligibility() );
				$this->assertSame( $action->asset_snapshot_eligibility, $rehydrated->asset_snapshot_eligibility );

				$alters = [];
				$queryFilter = $this->captureScanMetaAlters( $table, $alters );
				try {
					$this->runUpgradeWorker( $hook );
				}
				finally {
					\remove_filter( 'query', $queryFilter );
				}
				$this->assertSame( [], $alters );

				$this->assertNotFalse( $wpdb->query(
					"ALTER TABLE `{$table}` MODIFY COLUMN `meta` longtext NOT NULL COMMENT 'Scan Meta Info';"
				) );
				$alters = [];
				$queryFilter = $this->captureScanMetaAlters( $table, $alters );
				try {
					$this->runUpgradeWorker( $hook );
				}
				finally {
					\remove_filter( 'query', $queryFilter );
				}
				$this->assertSame( [], $alters );
				$this->assertSame( 'longtext', $this->scanMetaColumnType( $table ) );
			},
			function () use ( $con, $table, $targetDefinition, $previousVersion, $previousPersistRequired, $hook, $wpdb, &$committedScanIDs, $configStoreKey, $configStoreSnapshot ) :void {
				\remove_all_actions( $hook );
				\wp_clear_scheduled_hook( $hook, [ '0.0.1' ] );
				if ( $committedScanIDs !== [] ) {
					$ids = \implode( ',', \array_map( 'intval', $committedScanIDs ) );
					$this->assertNotFalse( $wpdb->query( "DELETE FROM `{$table}` WHERE `id` IN ({$ids})" ) );
				}
				$this->assertNotFalse( $wpdb->query( "ALTER TABLE `{$table}` MODIFY COLUMN `meta` {$targetDefinition};" ) );
				if ( $configStoreSnapshot === false ) {
					Services::WpGeneral()->deleteOption( $configStoreKey );
				}
				else {
					Services::WpGeneral()->updateOption( $configStoreKey, $configStoreSnapshot );
				}
				$con->cfg->previous_version = $previousVersion;
				$con->cfg->persist_required = $previousPersistRequired;
			}
		);
	}

	private function runUpgradeWorker( string $hook ) :void {
		$con = self::con();
		\remove_all_actions( $hook );
		$con->cfg->previous_version = '0.0.1';
		( new HandleUpgrade() )->execute();
		\do_action( $hook, '0.0.1' );
	}

	/**
	 * @param array<string,mixed> $meta
	 */
	private function insertScanWithMeta( array $meta ) :int {
		$record = $this->newScanRecordWithMeta( $meta );
		$this->assertTrue( self::con()->db_con->scans->getQueryInserter()->insert( $record ) );
		return (int)$GLOBALS[ 'wpdb' ]->insert_id;
	}

	/**
	 * @param array<string,mixed> $meta
	 */
	private function newScanRecordWithMeta( array $meta ) :object {
		$record = self::con()->db_con->scans->getRecord();
		$record->scan = 'afs';
		$record->status = ScanStatus::COMPLETED;
		$record->scope_type = 'full';
		$record->scope_key = '';
		$record->run_trigger = 'manual';
		$record->started_at = Services::Request()->ts() - 10;
		$record->last_process_at = Services::Request()->ts();
		$record->ready_at = Services::Request()->ts() - 10;
		$record->finished_at = Services::Request()->ts();
		$record->meta = $meta;
		return $record;
	}

	private function scanMetaColumnType( string $table ) :string {
		global $wpdb;
		$column = $wpdb->get_row(
			"SHOW COLUMNS FROM `{$table}` WHERE `Field`='meta';",
			\ARRAY_A
		);
		return \strtolower( (string)( $column[ 'Type' ] ?? '' ) );
	}

	private function captureScanMetaAlters( string $table, array &$alters ) :callable {
		$queryFilter = static function ( string $query ) use ( $table, &$alters ) :string {
			if ( \stripos( $query, "ALTER TABLE `{$table}` MODIFY COLUMN `meta`" ) !== false ) {
				$alters[] = $query;
			}
			return $query;
		};
		\add_filter( 'query', $queryFilter );
		return $queryFilter;
	}
}
