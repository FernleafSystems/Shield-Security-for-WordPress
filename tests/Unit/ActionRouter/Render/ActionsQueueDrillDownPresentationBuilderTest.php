<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueDrillDownPresentationBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueDrillDownPresentationBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
	}

	public function test_build_bucket_selection_reuses_shared_copy_contract() :void {
		$builder = new ActionsQueueDrillDownPresentationBuilder();

		$selection = $builder->buildBucketSelection(
			'critical',
			'fix_now',
			'critical_queue',
			'critical',
			'bi bi-exclamation-triangle-fill',
			2,
			'bucket_focus'
		);

		$this->assertSame( 'critical', $selection[ 'key' ] );
		$this->assertSame( 'fix_now', $selection[ 'label' ] );
		$this->assertSame( 'critical', $selection[ 'status' ] );
		$this->assertSame( 'bi bi-exclamation-triangle-fill', $selection[ 'icon_class' ] );
		$this->assertSame( 2, $selection[ 'item_count' ] );
		$this->assertSame( 'critical_queue', $selection[ 'header' ][ 'meta' ] ?? '' );
		$this->assertSame( 'bucket_focus', $selection[ 'header' ][ 'summary' ] ?? '' );
		$this->assertSame( 'bi bi-exclamation-triangle-fill', $selection[ 'header' ][ 'icon_class' ] ?? '' );
		$this->assertSame( 'critical', $selection[ 'header' ][ 'badge_status' ] ?? '' );
		$this->assertSame( 'critical', $selection[ 'header' ][ 'color_key' ] ?? '' );
		$this->assertSame( [], $selection[ 'header' ][ 'actions' ] ?? null );
		$selectionForJson = $selection;
		unset( $selectionForJson[ 'selection_json' ] );
		$this->assertSame( $selectionForJson, \json_decode( $selection[ 'selection_json' ], true ) );
	}

	public function test_build_bucket_selection_keeps_bucket_meta_when_bucket_is_good() :void {
		$builder = new ActionsQueueDrillDownPresentationBuilder();

		$selection = $builder->buildBucketSelection(
			'critical',
			'fix_now',
			'critical_queue',
			'good',
			'bi bi-exclamation-triangle-fill',
			0,
			'bucket_focus'
		);

		$this->assertSame( 'critical_queue', $selection[ 'header' ][ 'meta' ] ?? '' );
		$this->assertSame( 'good', $selection[ 'header' ][ 'badge_status' ] ?? '' );
		$this->assertSame( [], $selection[ 'header' ][ 'actions' ] ?? null );
	}

	public function test_build_group_selection_includes_detail_shell() :void {
		$builder = new ActionsQueueDrillDownPresentationBuilder();

		$selection = $builder->buildGroupSelection(
			'review_next',
			'maintenance',
			'maintenance_items',
			'warning',
			'bi bi-tools',
			1,
			'maintenance',
			[
				'render_slug' => 'maintenance_render',
			],
			'maintenance_focus'
		);

		$this->assertSame( 'maintenance', $selection[ 'key' ] );
		$this->assertSame( 'maintenance_items', $selection[ 'label' ] );
		$this->assertSame( 'warning', $selection[ 'status' ] );
		$this->assertSame( 'bi bi-tools', $selection[ 'icon_class' ] );
		$this->assertSame( 1, $selection[ 'item_count' ] );
		$this->assertSame( 'maintenance', $selection[ 'detail_shell' ] );
		$this->assertSame( [ 'render_slug' => 'maintenance_render' ], $selection[ 'detail_render_action' ] );
		$this->assertSame( 'maintenance_focus', $selection[ 'header' ][ 'summary' ] ?? '' );
		$this->assertSame( 'bi bi-tools', $selection[ 'header' ][ 'icon_class' ] ?? '' );
		$this->assertSame( 'warning', $selection[ 'header' ][ 'badge_status' ] ?? '' );
		$this->assertSame( 'warning', $selection[ 'header' ][ 'color_key' ] ?? '' );
		$this->assertSame( [], $selection[ 'header' ][ 'actions' ] ?? null );
		$selectionForJson = $selection;
		unset( $selectionForJson[ 'selection_json' ] );
		$this->assertSame( $selectionForJson, \json_decode( $selection[ 'selection_json' ], true ) );
	}

	public function test_queue_headers_carry_no_side_rail_actions() :void {
		$builder = new ActionsQueueDrillDownPresentationBuilder();

		$selection = $builder->buildGroupSelection(
			'fix_now',
			'plugins:example-plugin/example-plugin.php',
			'example_plugin',
			'critical',
			'bi bi-plug-fill',
			3,
			'direct_table',
			[
				'render_slug' => 'actions_queue_asset_file_status_detail',
			],
			'group_focus'
		);

		$this->assertSame( [], $selection[ 'header' ][ 'actions' ] );
		$this->assertSame( [], \json_decode( $selection[ 'selection_json' ], true )[ 'header' ][ 'actions' ] );
	}

}
