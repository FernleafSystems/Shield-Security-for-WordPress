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
			'Fix now',
			'critical',
			'bi bi-exclamation-triangle-fill',
			2,
			'Fix now contains 2 items that still need attention.'
		);

		$this->assertSame( 'critical', $selection[ 'key' ] );
		$this->assertSame( 'Fix now', $selection[ 'label' ] );
		$this->assertSame( 'critical', $selection[ 'status' ] );
		$this->assertSame( 'bi bi-exclamation-triangle-fill', $selection[ 'icon_class' ] );
		$this->assertSame( 2, $selection[ 'item_count' ] );
		$this->assertSame(
			[
				'compact_back_label' => 'Back to Fix now',
				'active_back_label'  => 'Back to Actions Queue',
				'title'              => 'Fix now',
				'meta'               => 'Critical queue',
				'summary'            => 'Fix now contains 2 items that still need attention.',
				'icon_class'         => 'bi bi-exclamation-triangle-fill',
				'badge'              => '2 items',
				'badge_status'       => 'critical',
			],
			$selection[ 'header' ]
		);
		$this->assertSame( 'Back to Fix now', $builder->buildBackLabel( 'Fix now' ) );
		$selectionForJson = $selection;
		unset( $selectionForJson[ 'selection_json' ] );
		$this->assertSame( $selectionForJson, \json_decode( $selection[ 'selection_json' ], true ) );
		$this->assertSame( 'Fix now contains 2 items that still need attention.', $builder->buildBucketFocusText( 'Fix now', 2 ) );
	}

	public function test_build_group_selection_includes_detail_shell() :void {
		$builder = new ActionsQueueDrillDownPresentationBuilder();

		$selection = $builder->buildGroupSelection(
			'Review next',
			'maintenance',
			'Maintenance Items',
			'warning',
			'bi bi-tools',
			1,
			'maintenance',
			'1 maintenance item needs review.'
		);

		$this->assertSame( 'maintenance', $selection[ 'key' ] );
		$this->assertSame( 'Maintenance Items', $selection[ 'label' ] );
		$this->assertSame( 'warning', $selection[ 'status' ] );
		$this->assertSame( 'bi bi-tools', $selection[ 'icon_class' ] );
		$this->assertSame( 1, $selection[ 'item_count' ] );
		$this->assertSame( 'maintenance', $selection[ 'detail_shell' ] );
		$this->assertSame(
			[
				'compact_back_label' => 'Back to Maintenance Items',
				'active_back_label'  => 'Back to Review next',
				'title'              => 'Maintenance Items',
				'summary'            => '1 maintenance item needs review.',
				'icon_class'         => 'bi bi-tools',
				'badge'              => '1 item',
				'badge_status'       => 'warning',
			],
			$selection[ 'header' ]
		);
		$selectionForJson = $selection;
		unset( $selectionForJson[ 'selection_json' ] );
		$this->assertSame( $selectionForJson, \json_decode( $selection[ 'selection_json' ], true ) );
	}
}
