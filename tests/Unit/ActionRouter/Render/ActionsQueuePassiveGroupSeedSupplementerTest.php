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
		$this->assertTrue( $seeds[ 0 ][ 'is_healthy' ] );
		$this->assertSame( 'neutral', $seeds[ 0 ][ 'status' ] );
		$this->assertSame( 'Pending', $seeds[ 0 ][ 'status_label_override' ] );
		$this->assertSame( 'Pending', $seeds[ 0 ][ 'header_badge_override' ] );
		$this->assertSame( 'neutral', $seeds[ 0 ][ 'header_badge_status_override' ] );
		$this->assertStringContainsString(
			'initial file locks are still being created',
			\strtolower( (string)$seeds[ 0 ][ 'header_summary_override' ] )
		);
	}
}
