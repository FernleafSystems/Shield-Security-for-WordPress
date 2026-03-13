<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons\SiteQuery;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class BuildAttentionItemsIntegrationTest extends ShieldIntegrationTestCase {

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->requireDb( 'file_locker' );

		$this->loginAsSecurityAdmin();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'ignored_maintenance_items',
		] );
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			static::con()->comps->file_locker->clearLocks();
			$this->restoreSelectedOptions( $this->optionsSnapshot );
		}
		\delete_site_transient( 'update_plugins' );
		parent::tear_down();
	}

	private function setPluginUpdateAvailable() :void {
		$updates = new \stdClass();
		$updates->response = [
			self::con()->base_file => (object)[
				'plugin'      => self::con()->base_file,
				'new_version' => self::con()->cfg->version().'.1',
			],
		];
		\set_site_transient( 'update_plugins', $updates );
	}

	public function test_build_includes_maintenance_item_and_summary() :void {
		$this->setPluginUpdateAvailable();

		$query = self::con()->comps->site_query->attention();
		$itemsByKey = [];
		foreach ( $query[ 'items' ] as $item ) {
			$itemsByKey[ $item[ 'key' ] ] = $item;
		}

		$this->assertArrayHasKey( 'wp_plugins_updates', $itemsByKey );
		$this->assertSame( 'maintenance', $itemsByKey[ 'wp_plugins_updates' ][ 'zone' ] );
		$this->assertSame( 1, $itemsByKey[ 'wp_plugins_updates' ][ 'count' ] );
		$this->assertGreaterThanOrEqual( 1, $query[ 'summary' ][ 'total' ] );
		$this->assertSame( 'warning', $query[ 'summary' ][ 'severity' ] );
		$this->assertFalse( $query[ 'summary' ][ 'is_all_clear' ] );
	}

	public function test_build_keeps_partially_ignored_maintenance_items_actionable() :void {
		$pluginFiles = $this->requireAtLeastInstalledPlugins( 2 );
		$this->setPluginUpdatesAvailableFor( $pluginFiles );

		self::con()->opts
			->optSet( 'ignored_maintenance_items', [
				'wp_plugins_updates' => [ $pluginFiles[ 0 ] ],
			] )
			->store();

		$query = self::con()->comps->site_query->attention();
		$itemsByKey = $this->indexItemsByKey( $query[ 'items' ] );
		$maintenanceItemsByKey = $this->indexItemsByKey( $query[ 'groups' ][ 'maintenance' ][ 'items' ] );

		$this->assertArrayHasKey( 'wp_plugins_updates', $itemsByKey );
		$this->assertArrayHasKey( 'wp_plugins_updates', $maintenanceItemsByKey );
		$this->assertSame( 'maintenance', $itemsByKey[ 'wp_plugins_updates' ][ 'zone' ] );
		$this->assertSame( 'maintenance', $itemsByKey[ 'wp_plugins_updates' ][ 'source' ] );
		$this->assertSame( 1, $itemsByKey[ 'wp_plugins_updates' ][ 'count' ] );
		$this->assertSame( 1, $itemsByKey[ 'wp_plugins_updates' ][ 'ignored_count' ] );
		$this->assertSame( 'warning', $itemsByKey[ 'wp_plugins_updates' ][ 'severity' ] );
		$this->assertTrue( $itemsByKey[ 'wp_plugins_updates' ][ 'supports_sub_items' ] );
		$this->assertSame( $itemsByKey[ 'wp_plugins_updates' ], $maintenanceItemsByKey[ 'wp_plugins_updates' ] );
		$this->assertSame(
			$this->sumItemCounts( $query[ 'groups' ][ 'maintenance' ][ 'items' ] ),
			$query[ 'groups' ][ 'maintenance' ][ 'total' ]
		);
		$this->assertSame( $this->sumItemCounts( $query[ 'items' ] ), $query[ 'summary' ][ 'total' ] );
		$this->assertSame(
			$query[ 'groups' ][ 'scans' ][ 'total' ] + $query[ 'groups' ][ 'maintenance' ][ 'total' ],
			$query[ 'summary' ][ 'total' ]
		);
		$this->assertSame( 'warning', $query[ 'summary' ][ 'severity' ] );
		$this->assertFalse( $query[ 'summary' ][ 'is_all_clear' ] );
	}

	public function test_build_maps_scan_items_and_hides_disabled_historical_results() :void {
		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'N' )
			->optSet( 'file_scan_areas', [] )
			->store();

		$pluginSlug = self::con()->base_file;
		$themeSlug = \wp_get_theme()->get_stylesheet();

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'    => 'wp-admin/admin.php',
			'is_in_core' => 1,
		] );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		$this->resetScanResultCountMemoization();

		$query = self::con()->comps->site_query->attention();

		$this->assertNotContains( 'wp_files', \array_column( $query[ 'groups' ][ 'scans' ][ 'items' ], 'key' ) );
		$this->assertNotContains( 'plugin_files', \array_column( $query[ 'groups' ][ 'scans' ][ 'items' ], 'key' ) );
		$this->assertNotContains( 'theme_files', \array_column( $query[ 'groups' ][ 'scans' ][ 'items' ], 'key' ) );
	}

	public function test_build_uses_asset_counts_and_file_locker_problems() :void {
		$this->enablePremiumCapabilities( [
			'scan_pluginsthemes_local',
			'scan_file_locker',
		] );

		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'file_scan_areas', [ 'plugins', 'themes' ] )
			->optSet( 'file_locker', [ 'wpconfig' ] )
			->store();

		$pluginSlug = self::con()->base_file;
		$themeSlug = \wp_get_theme()->get_stylesheet();

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'      => 'plugin-file-one.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'      => 'plugin-file-two.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $scanId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );

		$this->insertFileLockRecord( 'wpconfig', ABSPATH.'wp-config.php', \time() );
		self::con()->comps->file_locker->clearLocks();
		$this->resetScanResultCountMemoization();

		$query = self::con()->comps->site_query->attention();
		$itemsByKey = [];
		foreach ( $query[ 'groups' ][ 'scans' ][ 'items' ] as $item ) {
			$itemsByKey[ $item[ 'key' ] ] = $item;
		}

		$this->assertSame( 1, $itemsByKey[ 'plugin_files' ][ 'count' ] );
		$this->assertSame( 1, $itemsByKey[ 'theme_files' ][ 'count' ] );
		$this->assertSame( 1, $itemsByKey[ 'file_locker' ][ 'count' ] );
	}

	private function insertFileLockRecord( string $type, string $path, int $detectedAt = 0 ) :void {
		$handler = $this->requireDb( 'file_locker' );
		$record = $handler->getRecord();
		$record->type = $type;
		$record->path = $path;
		$record->hash_original = \sha1( $type.'-original' );
		$record->hash_current = \sha1( $type.'-current' );
		$record->public_key_id = 1;
		$record->cipher = 'aes-256-cbc';
		$record->content = 'encrypted-content-'.$type;
		$record->detected_at = $detectedAt;
		$handler->getQueryInserter()->insert( $record );
	}

	/**
	 * @return list<string>
	 */
	private function requireAtLeastInstalledPlugins( int $minimum ) :array {
		$pluginFiles = \array_values( \array_map(
			static fn( $file ) :string => (string)$file,
			\array_keys( Services::WpPlugins()->getPlugins() )
		) );
		\natsort( $pluginFiles );
		$pluginFiles = \array_values( $pluginFiles );

		if ( \count( $pluginFiles ) < $minimum ) {
			$this->markTestSkipped( 'Not enough installed plugins are available for this integration fixture.' );
		}

		return \array_slice( $pluginFiles, 0, $minimum );
	}

	/**
	 * @param list<string> $pluginFiles
	 */
	private function setPluginUpdatesAvailableFor( array $pluginFiles ) :void {
		$updates = new \stdClass();
		$updates->response = [];

		foreach ( $pluginFiles as $index => $pluginFile ) {
			$updates->response[ $pluginFile ] = (object)[
				'plugin'      => $pluginFile,
				'new_version' => self::con()->cfg->version().'.'.( $index + 1 ),
			];
		}

		\set_site_transient( 'update_plugins', $updates );
	}

	/**
	 * @param list<array<string,mixed>> $items
	 * @return array<string,array<string,mixed>>
	 */
	private function indexItemsByKey( array $items ) :array {
		$itemsByKey = [];
		foreach ( $items as $item ) {
			$itemsByKey[ (string)( $item[ 'key' ] ?? '' ) ] = $item;
		}
		return $itemsByKey;
	}

	/**
	 * @param list<array<string,mixed>> $items
	 */
	private function sumItemCounts( array $items ) :int {
		return (int)\array_sum( \array_column( $items, 'count' ) );
	}
}
