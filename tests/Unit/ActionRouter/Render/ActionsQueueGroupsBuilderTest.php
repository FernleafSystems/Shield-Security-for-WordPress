<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	Malware,
	Vulnerabilities
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueGroupsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueGroupsBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'number_format_i18n' )->alias( static fn( int $number ) :string => (string)$number );
	}

	public function test_build_groups_bucket_into_pane_aligned_cards() :void {
		$builder = new ActionsQueueGroupsBuilder();

		$data = $builder->build(
			'critical',
			[
				'items' => [
					[
						'key' => 'malware',
						'count' => 2,
						'severity' => 'critical',
					],
					[
						'key' => 'vulnerable_assets',
						'count' => 1,
						'severity' => 'critical',
					],
				],
			],
			[
				'scans' => [],
				'maintenance' => [],
			]
		);

		$this->assertSame( 'Fix now', $data[ 'bucket_selection' ][ 'label' ] );
		$this->assertSame( 'critical', $data[ 'bucket_selection' ][ 'status' ] );
		$this->assertSame( 3, $data[ 'bucket_selection' ][ 'item_count' ] );
		$bucketSelectionForJson = $data[ 'bucket_selection' ];
		unset( $bucketSelectionForJson[ 'selection_json' ] );
		$this->assertSame( $bucketSelectionForJson, \json_decode( $data[ 'bucket_selection_json' ], true ) );
		$this->assertSame( 'Fix now - 3 items', $data[ 'strip_text' ] );
		$this->assertSame( '3 items', $data[ 'strip_badge' ] );
		$this->assertSame( [ 'malware', 'vulnerabilities' ], \array_column( $data[ 'groups' ], 'key' ) );
		$this->assertSame( [ 'direct_table', 'direct_table' ], \array_column( $data[ 'groups' ], 'detail_shell' ) );
		$this->assertSame( [ 'expandable', 'linked' ], \array_column( $data[ 'groups' ], 'card_type' ) );
		$this->assertSame( Malware::class, $data[ 'groups' ][ 0 ][ 'render_action_class' ] );
		$this->assertSame( Vulnerabilities::class, $data[ 'groups' ][ 1 ][ 'render_action_class' ] );
		$this->assertSame( '2 suspected malware results need review.', $data[ 'groups' ][ 0 ][ 'narrative' ] );
		$this->assertSame( 'View 2 files', $data[ 'groups' ][ 0 ][ 'drill_hint' ] );
		$this->assertSame( [], $data[ 'groups' ][ 0 ][ 'maintenance_items' ] );
		$this->assertSame( 'Malware Detections - 2 items', $data[ 'groups' ][ 0 ][ 'strip_text' ] );
		$this->assertSame( '2 items', $data[ 'groups' ][ 0 ][ 'strip_badge' ] );
		$groupSelectionForJson = $data[ 'groups' ][ 0 ][ 'selection' ];
		unset( $groupSelectionForJson[ 'selection_json' ] );
		$this->assertSame(
			$groupSelectionForJson,
			\json_decode( $data[ 'groups' ][ 0 ][ 'selection_json' ], true )
		);
		$this->assertSame( 'Malware Detections', $data[ 'groups' ][ 0 ][ 'selection' ][ 'label' ] );
		$this->assertSame( 'direct_table', $data[ 'groups' ][ 0 ][ 'selection' ][ 'detail_shell' ] );
		$this->assertSame(
			'Review the flagged files and quarantine or delete them if they are confirmed malware.',
			$data[ 'groups' ][ 0 ][ 'next_move' ]
		);
	}

	public function test_build_group_returns_zero_state_fallback() :void {
		$builder = new ActionsQueueGroupsBuilder();

		$emptyGroup = $builder->buildGroup(
			'critical',
			'vulnerabilities',
			[
				'items' => [],
			],
			[
				'scans' => [],
				'maintenance' => [],
			]
		);

		$this->assertSame( 'vulnerabilities', $emptyGroup[ 'key' ] );
		$this->assertSame( 0, $emptyGroup[ 'item_count' ] );
		$this->assertSame( 'direct_table', $emptyGroup[ 'detail_shell' ] );
		$this->assertSame( 'linked', $emptyGroup[ 'card_type' ] );
		$this->assertSame( '', $emptyGroup[ 'drill_hint' ] );
		$this->assertSame( [], $emptyGroup[ 'maintenance_items' ] );
		$emptySelectionForJson = $emptyGroup[ 'selection' ];
		unset( $emptySelectionForJson[ 'selection_json' ] );
		$this->assertSame( $emptySelectionForJson, \json_decode( $emptyGroup[ 'selection_json' ], true ) );
		$this->assertSame( 'No matching items remain in this group.', $emptyGroup[ 'narrative' ] );
		$this->assertSame(
			'Go back to the grouped findings and pick another area to review.',
			$emptyGroup[ 'next_move' ]
		);
	}

	public function test_build_group_marks_asset_card_backed_groups() :void {
		$builder = new ActionsQueueGroupsBuilder();

		$pluginsGroup = $builder->buildGroup(
			'critical',
			'plugins',
			[
				'items' => [
					[
						'key'      => 'plugin_files',
						'count'    => 1,
						'severity' => 'critical',
					],
				],
			],
			[
				'scans'       => [],
				'maintenance' => [],
			]
		);

		$this->assertSame( 'plugins', $pluginsGroup[ 'key' ] );
		$this->assertSame( 'asset_cards', $pluginsGroup[ 'detail_shell' ] );
		$this->assertSame( 'expandable', $pluginsGroup[ 'card_type' ] );
		$this->assertSame( 'View 1 plugin', $pluginsGroup[ 'drill_hint' ] );
	}

	public function test_build_group_marks_theme_asset_card_hint_as_themes() :void {
		$builder = new ActionsQueueGroupsBuilder();

		$themesGroup = $builder->buildGroup(
			'critical',
			'themes',
			[
				'items' => [
					[
						'key'      => 'theme_files',
						'count'    => 3,
						'severity' => 'critical',
					],
				],
			],
			[
				'scans' => [],
				'maintenance' => [],
			]
		);

		$this->assertSame( 'themes', $themesGroup[ 'key' ] );
		$this->assertSame( 'asset_cards', $themesGroup[ 'detail_shell' ] );
		$this->assertSame( 'expandable', $themesGroup[ 'card_type' ] );
		$this->assertSame( 'View 3 themes', $themesGroup[ 'drill_hint' ] );
	}

	public function test_build_group_populates_maintenance_items_for_category_cards() :void {
		$builder = new ActionsQueueGroupsBuilder();

		$maintenanceGroup = $builder->buildGroup(
			'review',
			'maintenance',
			[
				'items' => [
					[
						'key'      => 'wp_updates',
						'count'    => 1,
						'severity' => 'warning',
					],
				],
			],
			[
				'scans' => [],
				'maintenance' => [
					[
						'key'               => 'wp_updates',
						'label'             => 'WordPress Version',
						'description'       => 'There is an upgrade available for WordPress.',
						'status'            => 'warning',
						'status_label'      => 'Warning',
						'status_icon_class' => 'bi bi-exclamation-circle-fill',
					],
				],
			]
		);

		$this->assertSame( 'maintenance', $maintenanceGroup[ 'key' ] );
		$this->assertSame( 'category', $maintenanceGroup[ 'card_type' ] );
		$this->assertSame( '', $maintenanceGroup[ 'drill_hint' ] );
		$this->assertSame(
			[
				[
					'icon_class' => 'bi bi-exclamation-circle-fill',
					'title'      => 'WordPress Version',
					'summary'    => 'There is an upgrade available for WordPress.',
				],
			],
			$maintenanceGroup[ 'maintenance_items' ]
		);
	}
}
