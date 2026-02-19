<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\AttentionItemsProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\BuiltMetersFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class AttentionItemsProviderIntegrationTest extends ShieldIntegrationTestCase {

	use BuiltMetersFixture;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->loginAsSecurityAdmin();
		$this->resetBuiltMetersCache();
		$this->setOverallConfigMeterComponents( [] );
	}

	public function tear_down() {
		$this->resetBuiltMetersCache();
		parent::tear_down();
	}

	public function test_build_action_items_includes_unprotected_maintenance_component() :void {
		$this->setOverallConfigMeterComponents( [
			[
				'slug'              => 'wp_updates',
				'is_protected'      => false,
				'title'             => 'WordPress Version',
				'title_unprotected' => 'WordPress Version',
				'desc_unprotected'  => 'There is an upgrade available for WordPress.',
				'href_full'         => self::con()->plugin_urls->adminHome(),
				'fix'               => 'Fix',
			],
		] );

		$items = ( new AttentionItemsProvider() )->buildActionItems();
		$keys = \array_column( $items, 'key' );
		$this->assertContains( 'wp_updates', $keys );
	}

	public function test_build_widget_rows_adds_summary_warning_item() :void {
		$rows = ( new AttentionItemsProvider() )->buildWidgetRows(
			10,
			[
				'warning' => [
					'text' => 'Meter warning text',
					'href' => self::con()->plugin_urls->adminHome(),
				],
			],
			'warning',
			self::con()->plugin_urls->adminHome()
		);

		$keys = \array_column( $rows[ 'items' ] ?? [], 'key' );
		$this->assertContains( 'meter_warning', $keys );
	}

	public function test_build_widget_rows_uses_generic_score_fallback_for_non_good_traffic() :void {
		$rows = ( new AttentionItemsProvider() )->buildWidgetRows(
			10,
			[
				'warning' => [],
			],
			'warning',
			self::con()->plugin_urls->adminHome()
		);

		$item = $rows[ 'items' ][ 0 ] ?? [];
		$this->assertSame( 'score_generic', (string)( $item[ 'key' ] ?? '' ) );
		$this->assertSame( 'warning', (string)( $item[ 'severity' ] ?? '' ) );
	}
}
