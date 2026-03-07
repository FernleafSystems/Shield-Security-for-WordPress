<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\AttentionItemsProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class AttentionItemsProviderIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->loginAsSecurityAdmin();
		\delete_site_transient( 'update_plugins' );
	}

	public function tear_down() {
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

	public function test_build_action_items_includes_operational_issue() :void {
		$this->setPluginUpdateAvailable();

		$items = ( new AttentionItemsProvider() )->buildActionItems();
		$itemsByKey = [];
		foreach ( $items as $item ) {
			$itemsByKey[ (string)( $item[ 'key' ] ?? '' ) ] = $item;
		}

		$this->assertArrayHasKey( 'wp_plugins_updates', $itemsByKey );
		$this->assertSame( 'maintenance', (string)( $itemsByKey[ 'wp_plugins_updates' ][ 'zone' ] ?? '' ) );
		$this->assertSame( 1, (int)( $itemsByKey[ 'wp_plugins_updates' ][ 'count' ] ?? 0 ) );
	}

	public function test_build_action_summary_reports_warning_for_maintenance_item() :void {
		$this->setPluginUpdateAvailable();

		$summary = ( new AttentionItemsProvider() )->buildActionSummary();
		$this->assertGreaterThanOrEqual( 1, (int)( $summary[ 'total' ] ?? 0 ) );
		$this->assertSame( 'warning', (string)( $summary[ 'severity' ] ?? '' ) );
		$this->assertFalse( (bool)( $summary[ 'is_all_clear' ] ?? true ) );
	}
}
