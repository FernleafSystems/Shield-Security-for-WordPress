<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageOperatorModeLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};

class PageOperatorModeLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $queuePayload = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		$this->installControllerStubWithQueuePayload( [] );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_actions_lane_uses_summary_contract_for_status_copy_and_breakdown() :void {
		$page = new PageOperatorModeLanding();
		$lane = $this->invokeNonPublicMethod( $page, 'buildActionsLane', [
			[
				'has_items'   => true,
				'total_items' => 1,
				'severity'    => 'critical',
				'icon_class'  => 'from-summary',
				'subtext'     => 'Last scan: 2 minutes ago',
			],
			[
				[
					'zone'         => 'scans',
					'severity'     => 'critical',
					'total'        => 2,
					'items'        => [
						[
							'key'         => '',
							'zone'        => '',
							'label'       => '',
							'count'       => 2,
							'severity'    => 'critical',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
				[
					'zone'         => 'maintenance',
					'severity'     => 'warning',
					'total'        => 1,
					'items'        => [
						[
							'key'         => '',
							'zone'        => '',
							'label'       => '',
							'count'       => 1,
							'severity'    => 'warning',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
			],
		] );

		$this->assertSame( 'actions', $lane[ 'mode' ] ?? '' );
		$this->assertSame( 'status', $lane[ 'indicator_type' ] ?? '' );
		$this->assertSame( 'critical', $lane[ 'indicator_severity' ] ?? '' );
		$this->assertSame( 'shield', $lane[ 'edge_status' ] ?? '' );
		$this->assertSame( ' has-critical', $lane[ 'extra_classes' ] ?? '' );
		$this->assertSame( '1 issue needs attention', $lane[ 'indicator_text' ] ?? '' );
		$this->assertSame( '2 critical - 1 warning', $lane[ 'indicator_subtext' ] ?? '' );
		$this->assertSame( 'bi bi-shield-x', $lane[ 'icon_class' ] ?? '' );
		$this->assertSame( '/admin/scans/overview', $lane[ 'href' ] ?? '' );
	}

	public function test_actions_lane_breakdown_uses_item_level_counts_within_same_zone() :void {
		$page = new PageOperatorModeLanding();
		$lane = $this->invokeNonPublicMethod( $page, 'buildActionsLane', [
			[
				'has_items'   => true,
				'total_items' => 3,
				'severity'    => 'critical',
				'icon_class'  => 'from-summary',
				'subtext'     => '',
			],
			[
				[
					'zone'         => 'scans',
					'severity'     => 'critical',
					'total'        => 3,
					'items'        => [
						[ 'severity' => 'critical', 'count' => 1 ],
						[ 'severity' => 'warning', 'count' => 2 ],
					],
				],
			],
		] );

		$this->assertSame( '1 critical - 2 warnings', $lane[ 'indicator_subtext' ] ?? '' );
	}

	public function test_actions_lane_all_clear_branch_uses_all_clear_copy() :void {
		$page = new PageOperatorModeLanding();
		$lane = $this->invokeNonPublicMethod( $page, 'buildActionsLane', [
			[
				'has_items'   => false,
				'total_items' => 0,
				'severity'    => 'good',
				'icon_class'  => 'bi bi-shield-check',
				'subtext'     => '',
			],
			[],
		] );

		$this->assertSame( 'good', $lane[ 'indicator_severity' ] ?? '' );
		$this->assertSame( 'shield', $lane[ 'edge_status' ] ?? '' );
		$this->assertSame( '', $lane[ 'extra_classes' ] ?? 'not-empty' );
		$this->assertSame( 'All Clear', $lane[ 'indicator_text' ] ?? '' );
		$this->assertSame( '', $lane[ 'indicator_subtext' ] ?? 'not-empty' );
		$this->assertSame( 'bi bi-shield-check', $lane[ 'icon_class' ] ?? '' );
	}

	public function test_investigate_configure_and_reports_lanes_use_expected_indicator_contracts() :void {
		$page = new PageOperatorModeLanding();

		$investigate = $this->invokeNonPublicMethod( $page, 'buildInvestigateLane', [
			[
				'active_count'        => 3,
				'recent_active_count' => 2,
			],
		] );
		$this->assertSame( 'status', $investigate[ 'indicator_type' ] ?? '' );
		$this->assertSame( 'info', $investigate[ 'indicator_severity' ] ?? '' );
		$this->assertSame( 'info', $investigate[ 'edge_status' ] ?? '' );
		$this->assertSame( '3 active sessions', $investigate[ 'indicator_text' ] ?? '' );
		$this->assertSame(
			[ '3 active sessions', '2 sessions in last 24h' ],
			\array_column( $investigate[ 'indicator_badges' ] ?? [], 'text' )
		);
		$this->assertSame( '/admin/activity/overview', $investigate[ 'href' ] ?? '' );

		$configure = $this->invokeNonPublicMethod( $page, 'buildConfigureLane', [ 95, 'good' ] );
		$this->assertSame( 'posture', $configure[ 'indicator_type' ] ?? '' );
		$this->assertSame( 'good', $configure[ 'edge_status' ] ?? '' );
		$this->assertSame( 95, $configure[ 'posture_percentage' ] ?? null );
		$this->assertSame( 'good', $configure[ 'posture_status' ] ?? '' );
		$this->assertSame( '95% configured', $configure[ 'posture_text' ] ?? '' );
		$this->assertSame( '/admin/zones/overview', $configure[ 'href' ] ?? '' );

		$reportsWithData = $this->invokeNonPublicMethod( $page, 'buildReportsLane', [
			[
				'count'            => 5,
				'latest_report_at' => 0,
				'latest_alert_at'  => 0,
			],
		] );
		$this->assertSame( 'info', $reportsWithData[ 'indicator_severity' ] ?? '' );
		$this->assertSame( 'warning', $reportsWithData[ 'edge_status' ] ?? '' );
		$this->assertSame( '5 reports', $reportsWithData[ 'indicator_text' ] ?? '' );
		$this->assertCount( 1, $reportsWithData[ 'indicator_badges' ] ?? [] );
		$this->assertSame( '5 reports', $reportsWithData[ 'indicator_badges' ][ 0 ][ 'text' ] ?? '' );
		$this->assertSame( '/admin/reports/overview', $reportsWithData[ 'href' ] ?? '' );

		$reportsFallback = $this->invokeNonPublicMethod( $page, 'buildReportsLane', [
			[
				'count'            => 0,
				'latest_report_at' => 0,
				'latest_alert_at'  => 0,
			],
		] );
		$this->assertSame( '0 reports', $reportsFallback[ 'indicator_text' ] ?? '' );
		$this->assertCount( 1, $reportsFallback[ 'indicator_badges' ] ?? [] );
	}

	public function test_investigate_session_summary_counts_active_and_recent_sessions() :void {
		$page = new class extends PageOperatorModeLanding {
			protected function getSessionsLoader() :LoadSessions {
				return new class extends LoadSessions {
					public function flat() :array {
						return [
							[
								'login'  => 189200,
								'shield' => [
									'last_activity_at' => 196400,
								],
							],
							[
								'login'  => 27200,
								'shield' => [
									'last_activity_at' => 27200,
								],
							],
							[
								'login' => 198200,
							],
						];
					}
				};
			}

			protected function getCurrentTimestamp() :int {
				return 200000;
			}
		};

		$summary = $this->invokeNonPublicMethod( $page, 'getInvestigateSessionSummary' );

		$this->assertSame( 3, $summary[ 'active_count' ] ?? null );
		$this->assertSame( 2, $summary[ 'recent_active_count' ] ?? null );
	}

	public function test_queue_summary_is_derived_from_attention_query_contract() :void {
		$page = new PageOperatorModeLanding();
		$summary = $this->invokeNonPublicMethod( $page, 'getQueueSummary', [ [
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => 3,
				'severity'     => 'warning',
				'is_all_clear' => false,
			],
			'items'        => [],
			'groups'       => [
				'scans'       => [ 'zone' => 'scans', 'total' => 0, 'severity' => 'good', 'items' => [] ],
				'maintenance' => [ 'zone' => 'maintenance', 'total' => 0, 'severity' => 'good', 'items' => [] ],
			],
		] ] );

		$this->assertTrue( $summary[ 'has_items' ] );
		$this->assertSame( 3, $summary[ 'total_items' ] );
		$this->assertSame( 'warning', $summary[ 'severity' ] );
		$this->assertSame( 'bi bi-shield-exclamation', $summary[ 'icon_class' ] );
		$this->assertSame( '', $summary[ 'subtext' ] );
	}

	public function test_queue_zone_groups_are_extracted_from_attention_query() :void {
		$page = new PageOperatorModeLanding();
		$zoneGroups = $this->invokeNonPublicMethod( $page, 'getQueueZoneGroups', [ [
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => 3,
				'severity'     => 'critical',
				'is_all_clear' => false,
			],
			'items'        => [],
			'groups'       => [
				'scans'       => [
					'zone'     => 'scans',
					'severity' => 'critical',
					'total'    => 2,
					'items'    => [
						[
							'key'         => '',
							'zone'        => '',
							'label'       => '',
							'count'       => 2,
							'severity'    => 'critical',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
				'maintenance' => [
					'zone'     => 'maintenance',
					'severity' => 'warning',
					'total'    => 1,
					'items'    => [
						[
							'key'         => '',
							'zone'        => '',
							'label'       => '',
							'count'       => 1,
							'severity'    => 'warning',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
			],
		] ] );

		$this->assertSame(
			[
				[
					'zone'         => 'scans',
					'severity'     => 'critical',
					'total'        => 2,
					'items'        => [
						[
							'key'         => '',
							'zone'        => '',
							'label'       => '',
							'count'       => 2,
							'severity'    => 'critical',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
				[
					'zone'         => 'maintenance',
					'severity'     => 'warning',
					'total'        => 1,
					'items'        => [
						[
							'key'         => '',
							'zone'        => '',
							'label'       => '',
							'count'       => 1,
							'severity'    => 'warning',
							'description' => '',
							'href'        => '',
							'action'      => '',
							'target'      => '',
						],
					],
				],
			],
			$zoneGroups
		);
	}

	public function test_queue_summary_marks_empty_attention_query_as_all_clear() :void {
		$page = new PageOperatorModeLanding();
		$summary = $this->invokeNonPublicMethod( $page, 'getQueueSummary', [ [
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => 0,
				'severity'     => 'good',
				'is_all_clear' => true,
			],
			'items'        => [],
			'groups'       => [
				'scans'       => [ 'zone' => 'scans', 'total' => 0, 'severity' => 'good', 'items' => [] ],
				'maintenance' => [ 'zone' => 'maintenance', 'total' => 0, 'severity' => 'good', 'items' => [] ],
			],
		] ] );

		$this->assertFalse( $summary[ 'has_items' ] );
		$this->assertSame( 0, $summary[ 'total_items' ] );
		$this->assertSame( 'good', $summary[ 'severity' ] );
		$this->assertSame( 'bi bi-shield-check', $summary[ 'icon_class' ] );
		$this->assertSame( '', $summary[ 'subtext' ] );
	}

	public function test_live_monitor_vars_use_current_compact_contract() :void {
		$page = new PageOperatorModeLanding();
		$vars = $this->invokeNonPublicMethod( $page, 'buildLiveMonitorVars' );

		$this->assertArrayHasKey( 'is_collapsed', $vars );
		$this->assertIsBool( $vars[ 'is_collapsed' ] ?? null );
		$this->assertSame( 'Live Monitor', $vars[ 'title' ] ?? '' );
		$this->assertSame( 'WP Activity', $vars[ 'activity' ] ?? '' );
		$this->assertSame( 'Live Traffic', $vars[ 'traffic' ] ?? '' );
		$this->assertSame( 'Waiting for live updates...', $vars[ 'loading' ] ?? '' );
		$this->assertArrayNotHasKey( 'minimize', $vars );
		$this->assertArrayNotHasKey( 'expand', $vars );
	}

	public function test_actions_queue_rows_follow_queue_scan_item_order_and_append_maintenance() :void {
		$rows = $this->invokeNonPublicMethod( new PageOperatorModeLanding(), 'buildActionsQueueRows', [
			[
				[
					'zone'         => 'scans',
					'severity'     => 'critical',
					'total'        => 23,
					'items'        => [
						[ 'key' => 'malware', 'label' => 'Malware', 'severity' => 'critical', 'count' => 4 ],
						[ 'key' => 'vulnerable_assets', 'label' => 'Vulnerabilities', 'severity' => 'critical', 'count' => 3 ],
						[ 'key' => 'wp_files', 'label' => 'WP Files', 'severity' => 'critical', 'count' => 2 ],
						[ 'key' => 'plugin_files', 'label' => 'Plugins', 'severity' => 'warning', 'count' => 5 ],
						[ 'key' => 'theme_files', 'label' => 'Themes', 'severity' => 'warning', 'count' => 1 ],
						[ 'key' => 'abandoned', 'label' => 'Abandoned Assets', 'severity' => 'warning', 'count' => 6 ],
						[ 'key' => 'file_locker', 'label' => 'File Locker', 'severity' => 'warning', 'count' => 2 ],
					],
				],
				[
					'zone'         => 'maintenance',
					'severity'     => 'warning',
					'total'        => 7,
					'items'        => [],
				],
			],
		] );

		$this->assertSame(
			[
				'malware',
				'vulnerable_assets',
				'wp_files',
				'plugin_files',
				'theme_files',
				'abandoned',
				'file_locker',
				'maintenance',
			],
			\array_column( $rows, 'key' )
		);
		$this->assertSame( [ 4, 3, 2, 5, 1, 6, 2, 7 ], \array_column( $rows, 'count' ) );
		$this->assertSame(
			[ 'critical', 'critical', 'critical', 'warning', 'warning', 'warning', 'warning', 'warning' ],
			\array_column( $rows, 'severity' )
		);
		$this->assertSame(
			[
				'bi bi-bug',
				'bi bi-shield-exclamation',
				'bi bi-wordpress',
				'bi bi-plug',
				'bi bi-brush',
				'bi bi-archive',
				'bi bi-file-lock2',
				'bi bi-wrench',
			],
			\array_column( $rows, 'icon_class' )
		);
	}

	public function test_actions_queue_rows_only_include_queue_scan_items_and_maintenance() :void {
		$rows = $this->invokeNonPublicMethod( new PageOperatorModeLanding(), 'buildActionsQueueRows', [
			[
				[
					'zone'         => 'scans',
					'severity'     => 'warning',
					'total'        => 2,
					'items'        => [
						[ 'key' => 'plugin_files', 'label' => 'Plugins', 'severity' => 'warning', 'count' => 2 ],
						[ 'key' => 'file_locker', 'label' => 'File Locker', 'severity' => 'good', 'count' => 0 ],
					],
				],
			],
		] );

		$this->assertSame(
			[ 'plugin_files', 'file_locker', 'maintenance' ],
			\array_column( $rows, 'key' )
		);
		$this->assertSame(
			[
				'plugin_files' => 'warning',
				'file_locker'  => 'good',
				'maintenance'  => 'good',
			],
			\array_combine( \array_column( $rows, 'key' ), \array_column( $rows, 'severity' ) )
		);
	}

	public function test_render_data_exposes_actions_queue_title_and_secondary_lanes() :void {
		$this->installControllerStubWithQueuePayload( [
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => 4,
				'severity'     => 'warning',
				'is_all_clear' => false,
			],
			'items'        => [],
			'groups'       => [
				'scans'       => [
					'zone'     => 'scans',
					'severity' => 'warning',
					'total'    => 3,
					'items'    => [
						[ 'key' => 'malware', 'label' => 'Malware', 'severity' => 'critical', 'count' => 2 ],
						[ 'key' => 'vulnerable_assets', 'label' => 'Vulnerabilities', 'severity' => 'good', 'count' => 0 ],
						[ 'key' => 'file_locker', 'label' => 'File Locker', 'severity' => 'warning', 'count' => 1 ],
					],
				],
				'maintenance' => [
					'zone'     => 'maintenance',
					'severity' => 'warning',
					'total'    => 1,
					'items'    => [],
				],
			],
		] );

		$renderData = $this->invokeNonPublicMethod( $this->newPage(), 'getRenderData' );

		$this->assertSame(
			PluginNavs::modeLabel( PluginNavs::MODE_ACTIONS ),
			$renderData[ 'strings' ][ 'title' ] ?? ''
		);
		$this->assertSame( 'actions', $renderData[ 'vars' ][ 'actions_lane' ][ 'mode' ] ?? '' );
		$this->assertSame(
			[ 'investigate', 'configure', 'reports' ],
			\array_column( $renderData[ 'vars' ][ 'secondary_lanes' ] ?? [], 'mode' )
		);
		$this->assertSame(
			[ 'malware', 'vulnerable_assets', 'file_locker', 'maintenance' ],
			\array_column( $renderData[ 'vars' ][ 'actions_queue_rows' ] ?? [], 'key' )
		);
	}

	private function newPage() :PageOperatorModeLanding {
		return new class( $this->queuePayload ) extends PageOperatorModeLanding {
			private array $attentionQuery;

			public function __construct( array $attentionQuery ) {
				$this->attentionQuery = $attentionQuery;
			}

			protected function getZonePosture() :array {
				return [
					'components' => [],
					'signals'    => [],
					'totals'     => [
						'score'        => 72,
						'max_weight'   => 100,
						'percentage'   => 72,
						'letter_score' => 'B',
					],
					'percentage' => 72,
					'severity'   => 'warning',
					'status'     => 'warning',
				];
			}

			protected function getSessionsLoader() :LoadSessions {
				return new class extends LoadSessions {
					public function flat() :array {
						return [];
					}
				};
			}

			protected function getCurrentTimestamp() :int {
				return 200000;
			}

			protected function buildAttentionQuery() :array {
				return $this->attentionQuery;
			}
		};
	}

	private function installControllerStubWithQueuePayload( array $queuePayload, array $reportsState = [] ) :void {
		$this->queuePayload = $queuePayload;
		$reportsState = \array_replace_recursive( [
			'reports_count'    => 0,
			'latest_report_at' => 0,
			'latest_alert_at'  => 0,
		], $reportsState );

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->comps = (object)[];
		$controller->db_con = (object)[
			'reports' => new class( $reportsState[ 'reports_count' ], $reportsState[ 'latest_report_at' ], $reportsState[ 'latest_alert_at' ] ) {
				private int $reportsCount;
				private int $latestReportAt;
				private int $latestAlertAt;

				public function __construct( int $reportsCount, int $latestReportAt, int $latestAlertAt ) {
					$this->reportsCount = $reportsCount;
					$this->latestReportAt = $latestReportAt;
					$this->latestAlertAt = $latestAlertAt;
				}

				public function getQuerySelector() :object {
					return new class( $this->reportsCount, $this->latestReportAt, $this->latestAlertAt ) {
						private int $reportsCount;
						private int $latestReportAt;
						private int $latestAlertAt;
						private ?string $type = null;

						public function __construct( int $reportsCount, int $latestReportAt, int $latestAlertAt ) {
							$this->reportsCount = $reportsCount;
							$this->latestReportAt = $latestReportAt;
							$this->latestAlertAt = $latestAlertAt;
						}

						public function addWhere( string $column, string $value, string $operator ) :self {
							return $this;
						}

						public function filterByType( string $type ) :self {
							$this->type = $type;
							return $this;
						}

						public function setOrderBy( string $column, string $direction = 'DESC', bool $replace = false ) :self {
							return $this;
						}

						public function count() :int {
							return $this->reportsCount;
						}

						public function first() :?object {
							$createdAt = $this->type === 'alt' ? $this->latestAlertAt : $this->latestReportAt;
							return $createdAt > 0 ? (object)[ 'created_at' => $createdAt ] : null;
						}
					};
				}
			},
		];
		PluginControllerInstaller::install( $controller );
	}
}
