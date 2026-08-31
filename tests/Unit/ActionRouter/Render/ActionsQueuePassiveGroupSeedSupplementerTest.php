<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueCompactSummaryRowBuilder,
	ActionsQueueGroupDefinitions,
	ActionsQueueGroupMaintenanceSource,
	ActionsQueueMaintenanceGroupSeedBuilder,
	ActionsQueuePassiveGroupSeedSupplementer,
	ActionsQueueScanResultScopeStateBuilder,
	ScansResultsRailTabAvailability
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\GetPendingFileLockDisplays;
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
		$maintenanceSource = $this->getMockBuilder( ActionsQueueGroupMaintenanceSource::class )
								  ->disableOriginalConstructor()
								  ->onlyMethods( [ 'itemsForBucket' ] )
								  ->getMock();
		$maintenanceSource->method( 'itemsForBucket' )->willReturn( [] );

		$supplementer = new ActionsQueuePassiveGroupSeedSupplementer(
			$definitions,
			$maintenanceSeedBuilder,
			$maintenanceSource,
			$this->makePendingFileLockDisplays( [
				[
					'file_key' => 'root_htaccess',
					'title'    => '.htaccess',
					'path'     => '/srv/www/.htaccess',
				],
				[
					'file_key' => 'wpconfig',
					'title'    => 'wp-config.php',
					'path'     => '/srv/www/wp-config.php',
				],
			] ),
			$this->makeScanResultScopeStateBuilder(),
			null,
			$this->makeRailTabAvailability()
		);

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
		$this->assertArrayHasKey( 'status_label_override', $seeds[ 0 ] );
		$this->assertNotSame( '', $seeds[ 0 ][ 'status_label_override' ] );
		$this->assertSame( $seeds[ 0 ][ 'status_label_override' ], $seeds[ 0 ][ 'header_badge_override' ] );
		$this->assertSame( 'neutral', $seeds[ 0 ][ 'header_badge_status_override' ] );
		$this->assertArrayHasKey( 'header_summary_override', $seeds[ 0 ] );
		$this->assertNotSame( '', $seeds[ 0 ][ 'header_summary_override' ] );
	}

	public function test_supplement_builds_healthy_aggregate_companion_only_when_active_base_exists() :void {
		$definitions = new ActionsQueueGroupDefinitions();
		$maintenanceSeedBuilder = new ActionsQueueMaintenanceGroupSeedBuilder(
			$definitions,
			new ActionsQueueCompactSummaryRowBuilder()
		);
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
			$maintenanceSource,
			$this->makeFailingPendingFileLockDisplays(),
			$this->makeScanResultScopeStateBuilder(),
			null,
			$this->makeRailTabAvailability()
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

	public function test_supplement_does_not_query_pending_file_locks_for_other_healthy_scan_groups() :void {
		$definitions = new ActionsQueueGroupDefinitions();
		$maintenanceSeedBuilder = new ActionsQueueMaintenanceGroupSeedBuilder(
			$definitions,
			new ActionsQueueCompactSummaryRowBuilder()
		);
		$maintenanceSource = $this->getMockBuilder( ActionsQueueGroupMaintenanceSource::class )
								  ->disableOriginalConstructor()
								  ->onlyMethods( [ 'itemsForBucket' ] )
								  ->getMock();
		$maintenanceSource->method( 'itemsForBucket' )->willReturn( [] );

		$seeds = ( new ActionsQueuePassiveGroupSeedSupplementer(
			$definitions,
			$maintenanceSeedBuilder,
			$maintenanceSource,
			$this->makeFailingPendingFileLockDisplays(),
			$this->makeScanResultScopeStateBuilder(),
			null,
			$this->makeRailTabAvailability()
		) )->supplement(
			'critical',
			[
				'attention_items' => [],
				'disabled_groups' => [],
			],
			[
				'scans'       => [
					[
						'key'               => 'wp_files',
						'label'             => 'WordPress Files',
						'description'       => 'WordPress files are healthy.',
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
		$this->assertSame( 'wordpress', $seeds[ 0 ][ 'key' ] );
		$this->assertSame( 'good', $seeds[ 0 ][ 'status' ] );
	}

	public function test_supplement_suppresses_empty_cloaked_plugin_detail() :void {
		$seeds = $this->newSupplementer()->supplement(
			'critical',
			[ 'attention_items' => [], 'disabled_groups' => [] ],
			[
				'scans' => [ [
					'key'               => 'hidden_plugins',
					'label'             => 'Cloaked Plugins',
					'description'       => 'No cloaked plugins are currently detected.',
					'drill_bucket'      => 'critical',
					'status'            => 'good',
					'status_label'      => 'Good',
					'status_icon_class' => 'bi bi-patch-check-fill',
					'has_useful_detail' => false,
				] ],
				'maintenance' => [],
			],
			[]
		);

		$this->assertCount( 1, $seeds );
		$this->assertSame( 'hidden_plugins', $seeds[ 0 ][ 'key' ] );
		$this->assertFalse( $seeds[ 0 ][ 'is_interactive_override' ] );
		$this->assertSame( [], $seeds[ 0 ][ 'render_action_data_override' ] );
		$this->assertSame( [], $seeds[ 0 ][ 'context_actions_override' ] );
		$this->assertTrue( $seeds[ 0 ][ 'suppress_detail_render_action_if_noninteractive' ] );
	}

	public function test_supplement_keeps_ignored_cloaked_plugin_detail_and_file_locker_default_detail() :void {
		$seeds = $this->newSupplementer( [] )->supplement(
			'critical',
			[ 'attention_items' => [], 'disabled_groups' => [] ],
			[
				'scans' => [
					[
						'key'               => 'hidden_plugins',
						'label'             => 'Cloaked Plugins',
						'description'       => 'No cloaked plugins are currently detected.',
						'drill_bucket'      => 'critical',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-patch-check-fill',
						'has_useful_detail' => true,
					],
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

		$byKey = \array_column( $seeds, null, 'key' );
		$this->assertTrue( $byKey[ 'hidden_plugins' ][ 'is_interactive_override' ] );
		$this->assertArrayNotHasKey( 'suppress_detail_render_action_if_noninteractive', $byKey[ 'hidden_plugins' ] );
		$this->assertTrue( $byKey[ 'file_locker' ][ 'is_interactive_override' ] );
		$this->assertNotSame( [], $byKey[ 'file_locker' ][ 'render_action_data_override' ] );
	}

	public function test_supplement_adds_interactive_direct_scan_seed_when_only_ignored_results_exist() :void {
		$definitions = new ActionsQueueGroupDefinitions();
		$maintenanceSeedBuilder = new ActionsQueueMaintenanceGroupSeedBuilder(
			$definitions,
			new ActionsQueueCompactSummaryRowBuilder()
		);
		$maintenanceSource = $this->getMockBuilder( ActionsQueueGroupMaintenanceSource::class )
								  ->disableOriginalConstructor()
								  ->onlyMethods( [ 'itemsForBucket' ] )
								  ->getMock();
		$maintenanceSource->method( 'itemsForBucket' )->willReturn( [] );

		$seeds = ( new ActionsQueuePassiveGroupSeedSupplementer(
			$definitions,
			$maintenanceSeedBuilder,
			$maintenanceSource,
			$this->makeFailingPendingFileLockDisplays(),
			$this->makeScanResultScopeStateBuilder( [
				'malware:malware'     => [
					'active_count'  => 0,
					'ignored_count' => 2,
				],
				'wordpress:wordpress' => [
					'active_count'  => 1,
					'ignored_count' => 3,
				],
			] ),
			null,
			$this->makeRailTabAvailability( [
				'malware'   => [
					'is_available'          => true,
					'show_in_actions_queue' => true,
				],
				'wordpress' => [
					'is_available'          => true,
					'show_in_actions_queue' => true,
				],
			] )
		) )->supplement(
			'critical',
			[
				'attention_items' => [],
				'disabled_groups' => [],
			],
			[
				'scans'       => [],
				'maintenance' => [],
			],
			[]
		);

		$this->assertCount( 1, $seeds );
		$this->assertSame( 'malware', $seeds[ 0 ][ 'key' ] );
		$this->assertSame( 'malware', $seeds[ 0 ][ 'definition_key' ] );
		$this->assertSame( 0, $seeds[ 0 ][ 'item_count' ] );
		$this->assertSame( 'good', $seeds[ 0 ][ 'status' ] );
		$this->assertTrue( $seeds[ 0 ][ 'is_interactive_override' ] );
		$this->assertNotSame( [], $seeds[ 0 ][ 'render_action_data_override' ] );
		$this->assertSame( [], $seeds[ 0 ][ 'context_actions_override' ] );
	}

	public function test_supplement_marks_only_upgrade_required_protection_groups_for_pro_upsell() :void {
		$eligibleKeys = [ 'malware', 'vulnerabilities', 'plugins', 'themes', 'file_locker' ];
		$disabledGroups = [];
		foreach ( $eligibleKeys as $key ) {
			$disabledGroups[ $key ] = [
				'disabled_reason'  => 'upgrade_required',
				'disabled_message' => $key.' requires an upgrade.',
			];
		}
		$disabledGroups[ 'wordpress' ] = [
			'disabled_reason'  => 'upgrade_required',
			'disabled_message' => 'WordPress core scanning requires an upgrade.',
		];

		$seeds = $this->newSupplementer()->supplement(
			'critical',
			[ 'attention_items' => [], 'disabled_groups' => $disabledGroups ],
			[ 'scans' => [], 'maintenance' => [] ],
			[]
		);
		$seedsByKey = \array_column( $seeds, null, 'key' );
		$upsellKeys = \array_keys( \array_filter(
			$seedsByKey,
			static fn( array $seed ) :bool => !empty( $seed[ 'is_pro_upsell' ] )
		) );

		$this->assertSame( $eligibleKeys, $upsellKeys );
		$this->assertArrayNotHasKey( 'is_pro_upsell', $seedsByKey[ 'wordpress' ] );

		$notEnabledSeeds = $this->newSupplementer()->supplement(
			'critical',
			[
				'attention_items' => [],
				'disabled_groups' => [
					'file_locker' => [
						'disabled_reason'  => 'not_enabled',
						'disabled_message' => 'File Locker is not enabled.',
					],
				],
			],
			[ 'scans' => [], 'maintenance' => [] ],
			[]
		);

		$this->assertArrayNotHasKey( 'is_pro_upsell', $notEnabledSeeds[ 0 ] );
	}

	private function makePendingFileLockDisplays( array $displays ) :GetPendingFileLockDisplays {
		return new class( $displays ) extends GetPendingFileLockDisplays {

			private array $displays;

			public function __construct( array $displays ) {
				$this->displays = $displays;
			}

			public function run() :array {
				return $this->displays;
			}
		};
	}

	private function newSupplementer( ?array $pendingFileLockDisplays = null ) :ActionsQueuePassiveGroupSeedSupplementer {
		$definitions = new ActionsQueueGroupDefinitions();
		$maintenanceSeedBuilder = new ActionsQueueMaintenanceGroupSeedBuilder(
			$definitions,
			new ActionsQueueCompactSummaryRowBuilder()
		);
		$maintenanceSource = $this->getMockBuilder( ActionsQueueGroupMaintenanceSource::class )
								  ->disableOriginalConstructor()
								  ->onlyMethods( [ 'itemsForBucket' ] )
								  ->getMock();
		$maintenanceSource->method( 'itemsForBucket' )->willReturn( [] );

		return new ActionsQueuePassiveGroupSeedSupplementer(
			$definitions,
			$maintenanceSeedBuilder,
			$maintenanceSource,
			$pendingFileLockDisplays === null
				? $this->makeFailingPendingFileLockDisplays()
				: $this->makePendingFileLockDisplays( $pendingFileLockDisplays ),
			$this->makeScanResultScopeStateBuilder(),
			null,
			$this->makeRailTabAvailability()
		);
	}

	private function makeFailingPendingFileLockDisplays() :GetPendingFileLockDisplays {
		return new class extends GetPendingFileLockDisplays {
			public function run() :array {
				throw new \LogicException( 'Pending File Locker provider should not be queried.' );
			}
		};
	}

	private function makeScanResultScopeStateBuilder( array $countsByScope = [] ) :ActionsQueueScanResultScopeStateBuilder {
		return new class( $countsByScope ) extends ActionsQueueScanResultScopeStateBuilder {

			private array $countsByScope;

			public function __construct( array $countsByScope ) {
				$this->countsByScope = $countsByScope;
			}

			public function buildCountsForActionScope( string $type, string $file ) :array {
				$scopeKey = $type.':'.$file;
				$counts = $this->countsByScope[ $scopeKey ] ?? [];

				return [
					'scope'         => [
						'type' => $type,
						'file' => $file,
					],
					'active_count'  => (int)( $counts[ 'active_count' ] ?? 0 ),
					'ignored_count' => (int)( $counts[ 'ignored_count' ] ?? 0 ),
				];
			}
		};
	}

	private function makeRailTabAvailability( array $states = [] ) :ScansResultsRailTabAvailability {
		return new class( $states ) extends ScansResultsRailTabAvailability {

			private array $states;

			public function __construct( array $states ) {
				$this->states = $states;
			}

			public function build( string $tabKey ) :array {
				return \array_merge( [
					'is_available'          => false,
					'show_in_actions_queue' => false,
					'show_in_fix_now'       => false,
					'disabled_reason'       => '',
					'disabled_message'      => '',
					'disabled_status'       => 'neutral',
					'disabled_actions'      => [],
				], $this->states[ $tabKey ] ?? [] );
			}
		};
	}
}
