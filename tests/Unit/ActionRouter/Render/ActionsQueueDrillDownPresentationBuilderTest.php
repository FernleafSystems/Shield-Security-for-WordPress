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
			'Critical queue',
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
				'meta'               => 'Critical queue',
				'breadcrumb_label'   => 'Fix now',
				'title'              => 'Fix now',
				'summary'            => 'Fix now contains 2 items that still need attention.',
				'focus'              => 'Critical queue',
				'next_step'          => 'Choose one grouped finding to review the matching results.',
				'icon_class'         => 'bi bi-exclamation-triangle-fill',
				'badge'              => '2 items',
				'badge_status'       => 'critical',
				'color_key'          => 'critical',
				'actions'            => [],
			],
			$selection[ 'header' ]
		);
		$this->assertSame( 'Back to Fix now', $builder->buildBackLabel( 'Fix now' ) );
		$selectionForJson = $selection;
		unset( $selectionForJson[ 'selection_json' ] );
		$this->assertSame( $selectionForJson, \json_decode( $selection[ 'selection_json' ], true ) );
		$this->assertSame( 'Fix now contains 2 items that still need attention.', $builder->buildBucketFocusText( 'Fix now', 2 ) );
	}

	public function test_build_bucket_selection_keeps_bucket_meta_when_bucket_is_good() :void {
		$builder = new ActionsQueueDrillDownPresentationBuilder();

		$selection = $builder->buildBucketSelection(
			'critical',
			'Fix now',
			'Critical queue',
			'good',
			'bi bi-exclamation-triangle-fill',
			0,
			'Everything in this bucket is currently looking good.'
		);

		$this->assertSame( 'Critical queue', $selection[ 'header' ][ 'meta' ] ?? '' );
		$this->assertSame( 'good', $selection[ 'header' ][ 'badge_status' ] ?? '' );
		$this->assertSame( [], $selection[ 'header' ][ 'actions' ] ?? null );
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
				'meta'               => '',
				'breadcrumb_label'   => 'Maintenance Items',
				'title'              => 'Maintenance Items',
				'summary'            => '1 maintenance item needs review.',
				'focus'              => '1 item',
				'next_step'          => 'Review the scoped results and complete the next action.',
				'icon_class'         => 'bi bi-tools',
				'badge'              => '1 item',
				'badge_status'       => 'warning',
				'color_key'          => 'warning',
				'actions'            => [],
			],
			$selection[ 'header' ]
		);
		$selectionForJson = $selection;
		unset( $selectionForJson[ 'selection_json' ] );
		$this->assertSame( $selectionForJson, \json_decode( $selection[ 'selection_json' ], true ) );
	}

	public function test_build_group_selection_keeps_context_actions_in_header_and_selection_json() :void {
		$builder = new ActionsQueueDrillDownPresentationBuilder();

		$selection = $builder->buildGroupSelection(
			'Fix now',
			'plugins:example-plugin/example-plugin.php',
			'Example Plugin',
			'critical',
			'bi bi-plug-fill',
			3,
			'direct_table',
			'3 files need review.',
			[
				[
					'kind'             => 'ajax',
					'label'            => 'Ignore All Results',
					'type'             => 'deactivate',
					'icon_class'       => 'bi bi-eye-slash-fill',
					'ajax_action_json' => '{"sub_action":"ignore_all"}',
					'confirm_text'     => 'Ignore all active results for Example Plugin?',
				],
			]
		);

		$this->assertSame( 'Ignore All Results', $selection[ 'header' ][ 'actions' ][ 0 ][ 'label' ] ?? '' );
		$this->assertSame( 'ajax', $selection[ 'header' ][ 'actions' ][ 0 ][ 'kind' ] ?? '' );
		$this->assertSame(
			$selection[ 'header' ][ 'actions' ],
			\json_decode( $selection[ 'selection_json' ], true )[ 'header' ][ 'actions' ] ?? []
		);
	}
}
