<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class BuildAttentionItemsIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->requireDb( 'file_locker' );

		$this->loginAsSecurityAdmin();
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			static::con()->comps->file_locker->clearLocks();
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

		$query = ( new BuildAttentionItems() )->build();
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

		$query = ( new BuildAttentionItems() )->build();

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

		$query = ( new BuildAttentionItems() )->build();
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
}
