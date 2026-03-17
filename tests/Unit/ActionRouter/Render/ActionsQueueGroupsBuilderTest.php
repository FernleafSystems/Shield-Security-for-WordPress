<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\{
	Maintenance,
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
		$this->assertSame( 'Fix now - 3 items', $data[ 'strip_text' ] );
		$this->assertSame( '3 items', $data[ 'strip_badge' ] );
		$this->assertSame( [ 'malware', 'vulnerabilities' ], \array_column( $data[ 'groups' ], 'key' ) );
		$this->assertSame( [ 'direct_table', 'direct_table' ], \array_column( $data[ 'groups' ], 'detail_shell' ) );
		$this->assertSame( Malware::class, $data[ 'groups' ][ 0 ][ 'render_action_class' ] );
		$this->assertSame( Vulnerabilities::class, $data[ 'groups' ][ 1 ][ 'render_action_class' ] );
		$this->assertSame( '2 suspected malware results need review.', $data[ 'groups' ][ 0 ][ 'narrative' ] );
		$this->assertSame( 'Malware Detections - 2 items', $data[ 'groups' ][ 0 ][ 'strip_text' ] );
		$this->assertSame( '2 items', $data[ 'groups' ][ 0 ][ 'strip_badge' ] );
		$this->assertSame( 'Malware Detections', $data[ 'groups' ][ 0 ][ 'selection' ][ 'label' ] );
		$this->assertSame( 'direct_table', $data[ 'groups' ][ 0 ][ 'selection' ][ 'detail_shell' ] );
		$this->assertSame(
			'Review the flagged files and quarantine or delete them if they are confirmed malware.',
			$data[ 'groups' ][ 0 ][ 'next_move' ]
		);
	}

	public function test_build_group_returns_maintenance_group_and_zero_state_fallback() :void {
		$builder = new ActionsQueueGroupsBuilder();

		$maintenanceGroup = $builder->buildGroup(
			'later',
			'maintenance',
			[
				'items' => [],
			],
			[
				'scans' => [],
				'maintenance' => [
					[
						'key' => 'system_php_version',
						'label' => 'PHP Version',
						'description' => 'Healthy',
						'status' => 'good',
						'status_label' => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
					[
						'key' => 'system_ssl_certificate',
						'label' => 'SSL Certificate',
						'description' => 'Healthy',
						'status' => 'neutral',
						'status_label' => 'Neutral',
						'status_icon_class' => 'bi bi-info-circle-fill',
					],
				],
			]
		);
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

		$this->assertSame( 'maintenance', $maintenanceGroup[ 'key' ] );
		$this->assertSame( 2, $maintenanceGroup[ 'item_count' ] );
		$this->assertSame( 'maintenance', $maintenanceGroup[ 'detail_shell' ] );
		$this->assertSame( Maintenance::class, $maintenanceGroup[ 'render_action_class' ] );
		$this->assertSame( '2 maintenance checks are currently healthy.', $maintenanceGroup[ 'narrative' ] );

		$this->assertSame( 'vulnerabilities', $emptyGroup[ 'key' ] );
		$this->assertSame( 0, $emptyGroup[ 'item_count' ] );
		$this->assertSame( 'direct_table', $emptyGroup[ 'detail_shell' ] );
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
	}
}
