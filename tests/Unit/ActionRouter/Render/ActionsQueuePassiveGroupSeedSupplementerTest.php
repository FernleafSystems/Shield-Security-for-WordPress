<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueCompactSummaryRowBuilder,
	ActionsQueueGroupDefinitions,
	ActionsQueueGroupMaintenanceSource,
	ActionsQueueGroupScanSource,
	ActionsQueueMaintenanceGroupSeedBuilder,
	ActionsQueuePassiveGroupSeedSupplementer
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueuePassiveGroupSeedSupplementerTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'number_format_i18n' )->alias( static fn( int $number ) :string => (string)$number );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
	}

	public function test_supplement_marks_healthy_file_locker_group_as_pending_when_initial_locks_are_outstanding() :void {
		$definitions = new ActionsQueueGroupDefinitions();
		$maintenanceSeedBuilder = new ActionsQueueMaintenanceGroupSeedBuilder(
			$definitions,
			new ActionsQueueCompactSummaryRowBuilder()
		);
		$scanSource = $this->getMockBuilder( ActionsQueueGroupScanSource::class )
						   ->disableOriginalConstructor()
						   ->getMock();
		$maintenanceSource = $this->getMockBuilder( ActionsQueueGroupMaintenanceSource::class )
								  ->disableOriginalConstructor()
								  ->onlyMethods( [ 'itemsForBucket' ] )
								  ->getMock();
		$maintenanceSource->method( 'itemsForBucket' )->willReturn( [] );

		$supplementer = new class(
			$definitions,
			$maintenanceSeedBuilder,
			$scanSource,
			$maintenanceSource
		) extends ActionsQueuePassiveGroupSeedSupplementer {
			protected function getPendingFileLockerCount() :int {
				return 2;
			}
		};

		$seeds = $supplementer->supplement(
			'critical',
			[
				'attention_items'  => [],
				'disabled_groups'  => [],
			],
			[
				'scans'       => [
					[
						'key'               => 'file_locker',
						'label'             => 'File Locker',
						'description'       => 'Locked files are healthy.',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
					],
				],
				'maintenance' => [],
			],
			[]
		);

		$this->assertCount( 1, $seeds );
		$this->assertSame( 'file_locker', $seeds[ 0 ][ 'key' ] );
		$this->assertSame( 'neutral', $seeds[ 0 ][ 'status' ] );
		$this->assertNotSame( '', $seeds[ 0 ][ 'status_label_override' ] ?? '' );
		$this->assertSame( $seeds[ 0 ][ 'status_label_override' ], $seeds[ 0 ][ 'header_badge_override' ] );
		$this->assertSame( 'neutral', $seeds[ 0 ][ 'header_badge_status_override' ] );
		$this->assertNotSame( '', $seeds[ 0 ][ 'header_summary_override' ] ?? '' );
	}

	public function test_supplement_builds_healthy_aggregate_companion_only_when_active_base_exists() :void {
		$definitions = new ActionsQueueGroupDefinitions();
		$maintenanceSeedBuilder = new ActionsQueueMaintenanceGroupSeedBuilder(
			$definitions,
			new ActionsQueueCompactSummaryRowBuilder()
		);
		$scanSource = $this->getMockBuilder( ActionsQueueGroupScanSource::class )
						   ->disableOriginalConstructor()
						   ->getMock();
		$maintenanceSource = $this->getMockBuilder( ActionsQueueGroupMaintenanceSource::class )
								  ->disableOriginalConstructor()
								  ->onlyMethods( [ 'itemsForBucket' ] )
								  ->getMock();
		$maintenanceSource->method( 'itemsForBucket' )->willReturn( [
			[
				'key'           => 'default_admin_user',
				'zone'          => 'maintenance',
				'label'         => 'Default Admin User',
				'icon_class'    => 'bi bi-person-fill-lock',
				'count'         => 0,
				'severity'      => 'good',
				'drill_bucket'  => 'review',
				'description'   => 'The default admin user is no longer available.',
				'href'          => '/wp-admin/users.php',
				'action'        => 'Manage Users',
				'target'        => '',
				'cta'           => [
					'href'  => '/wp-admin/users.php',
					'label' => 'Manage Users',
				],
				'toggle_action' => [],
				'expansion'     => [],
			],
			[
				'key'           => 'wp_db_password',
				'zone'          => 'maintenance',
				'label'         => 'MySQL DB Password',
				'icon_class'    => 'bi bi-database-fill-lock',
				'count'         => 0,
				'severity'      => 'good',
				'drill_bucket'  => 'review',
				'description'   => 'The database password is strong.',
				'href'          => '',
				'action'        => '',
				'target'        => '',
				'cta'           => [],
				'toggle_action' => [],
				'expansion'     => [],
			],
		] );

		$supplementer = new ActionsQueuePassiveGroupSeedSupplementer(
			$definitions,
			$maintenanceSeedBuilder,
			$scanSource,
			$maintenanceSource
		);
		$bucketSource = [
			'attention_items' => [],
			'disabled_groups' => [],
		];
		$assessmentRowsByZone = [
			'scans'       => [],
			'maintenance' => [],
		];

		$companionSeeds = $supplementer->supplement(
			'review',
			$bucketSource,
			$assessmentRowsByZone,
			[ 'maintenance_wordpress' => true ]
		);

		$this->assertCount( 1, $companionSeeds );
		$this->assertSame( 'maintenance_wordpress:healthy', $companionSeeds[ 0 ][ 'key' ] );
		$this->assertSame( 'maintenance_wordpress', $companionSeeds[ 0 ][ 'definition_key' ] );
		$this->assertSame( 'WordPress', $companionSeeds[ 0 ][ 'label' ] );
		$this->assertSame( 'good', $companionSeeds[ 0 ][ 'status' ] );
		$this->assertSame( 2, $companionSeeds[ 0 ][ 'item_count' ] );
		$this->assertSame(
			[ 'Default Admin User', 'MySQL DB Password' ],
			\array_column( $companionSeeds[ 0 ][ 'maintenance_rows' ], 'title' )
		);

		$baseSeeds = $supplementer->supplement( 'review', $bucketSource, $assessmentRowsByZone, [] );

		$this->assertCount( 1, $baseSeeds );
		$this->assertSame( 'maintenance_wordpress', $baseSeeds[ 0 ][ 'key' ] );
		$this->assertSame( 'maintenance_wordpress', $baseSeeds[ 0 ][ 'definition_key' ] );
	}
}
