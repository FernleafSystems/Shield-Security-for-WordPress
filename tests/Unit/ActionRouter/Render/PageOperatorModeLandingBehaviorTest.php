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

	/** @var array{enabled:array<string,bool>,counts:array<string,int>,reports_count:int,latest_report_at:int,latest_alert_at:int} */
	private array $scanState = [];

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
					'slug'         => 'scans',
					'label'        => 'Scans',
					'icon_class'   => 'bi bi-scans',
					'severity'     => 'critical',
					'total_issues' => 2,
					'items'        => [
						[ 'severity' => 'critical', 'count' => 2 ],
					],
				],
				[
					'slug'         => 'maintenance',
					'label'        => 'Maintenance',
					'icon_class'   => 'bi bi-maintenance',
					'severity'     => 'warning',
					'total_issues' => 1,
					'items'        => [
						[ 'severity' => 'warning', 'count' => 1 ],
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
					'slug'         => 'scans',
					'label'        => 'Scans',
					'icon_class'   => 'bi bi-scans',
					'severity'     => 'critical',
					'total_issues' => 3,
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

	public function test_queue_summary_is_loaded_from_render_data_contract_path() :void {
		$payload = [
			'vars'        => [
				'summary' => [
					'has_items'   => false,
					'total_items' => 0,
					'severity'    => 'good',
					'icon_class'  => 'wrong-path',
					'subtext'     => 'wrong-path',
				],
			],
			'render_data' => [
				'vars' => [
					'summary' => [
						'has_items'   => true,
						'total_items' => 3,
						'severity'    => 'warning',
						'icon_class'  => 'from-render-data',
						'subtext'     => 'from-render-data',
					],
				],
			],
		];

		$page = new PageOperatorModeLanding();
		$summary = $this->invokeNonPublicMethod( $page, 'getQueueSummary', [ $payload ] );

		$this->assertTrue( (bool)( $summary[ 'has_items' ] ?? false ) );
		$this->assertSame( 3, $summary[ 'total_items' ] ?? null );
		$this->assertSame( 'warning', $summary[ 'severity' ] ?? '' );
		$this->assertSame( 'from-render-data', $summary[ 'icon_class' ] ?? '' );
		$this->assertSame( 'from-render-data', $summary[ 'subtext' ] ?? '' );
	}

	public function test_queue_zone_groups_are_extracted_and_normalized() :void {
		$page = new PageOperatorModeLanding();
		$zoneGroups = $this->invokeNonPublicMethod( $page, 'getQueueZoneGroups', [ [
			'render_data' => [
				'vars' => [
					'zone_groups' => [
						[
							'slug'         => 'scans',
							'label'        => 'Scans',
							'icon_class'   => 'bi bi-scans',
							'severity'     => 'critical',
							'total_issues' => 2,
							'items'        => [
								[ 'severity' => 'critical', 'count' => 2 ],
							],
						],
						[
							'slug'         => 'maintenance',
							'label'        => 'Maintenance',
							'icon_class'   => 'bi bi-maintenance',
							'severity'     => 'warning',
							'total_issues' => 1,
							'items'        => [
								[ 'severity' => 'warning', 'count' => 1 ],
							],
						],
					],
				],
			],
		] ] );

		$this->assertSame(
			[
				[
					'slug'         => 'scans',
					'label'        => 'Scans',
					'icon_class'   => 'bi bi-scans',
					'severity'     => 'critical',
					'total_issues' => 2,
					'items'        => [
						[ 'severity' => 'critical', 'count' => 2 ],
					],
				],
				[
					'slug'         => 'maintenance',
					'label'        => 'Maintenance',
					'icon_class'   => 'bi bi-maintenance',
					'severity'     => 'warning',
					'total_issues' => 1,
					'items'        => [
						[ 'severity' => 'warning', 'count' => 1 ],
					],
				],
			],
			$zoneGroups
		);
	}

	public function test_queue_summary_falls_back_to_safe_defaults_for_missing_fields() :void {
		$page = new PageOperatorModeLanding();
		$summary = $this->invokeNonPublicMethod( $page, 'getQueueSummary', [ [] ] );

		$this->assertFalse( (bool)( $summary[ 'has_items' ] ?? true ) );
		$this->assertSame( 0, $summary[ 'total_items' ] ?? null );
		$this->assertSame( 'good', $summary[ 'severity' ] ?? '' );
		$this->assertSame( 'bi bi-shield-check', $summary[ 'icon_class' ] ?? '' );
		$this->assertSame( '', $summary[ 'subtext' ] ?? 'not-empty' );
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

	public function test_actions_queue_rows_follow_risk_first_order_and_counts_contract() :void {
		$this->installControllerStubWithQueuePayload(
			[],
			[
				'enabled' => [
					'malware'           => true,
					'vulnerable_assets' => true,
					'wp_files'          => true,
					'plugin_files'      => true,
					'theme_files'       => true,
					'abandoned'         => true,
				],
				'counts' => [
					'malware'           => 4,
					'vulnerable_assets' => 3,
					'wp_files'          => 2,
					'plugin_files'      => 5,
					'theme_files'       => 1,
					'abandoned'         => 6,
				],
			]
		);

		$rows = $this->invokeNonPublicMethod( new PageOperatorModeLanding(), 'buildActionsQueueRows', [
			[
				[
					'slug'         => 'maintenance',
					'label'        => 'Maintenance',
					'icon_class'   => 'bi bi-maintenance',
					'severity'     => 'warning',
					'total_issues' => 7,
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
				'maintenance',
			],
			\array_column( $rows, 'key' )
		);
		$this->assertSame( [ 4, 3, 2, 5, 1, 6, 7 ], \array_column( $rows, 'count' ) );
		$this->assertSame(
			[ 'critical', 'critical', 'critical', 'warning', 'warning', 'warning', 'warning' ],
			\array_column( $rows, 'severity' )
		);
	}

	public function test_actions_queue_rows_hide_disabled_scanners_and_keep_enabled_zero_rows() :void {
		$this->installControllerStubWithQueuePayload(
			[],
			[
				'enabled' => [
					'malware'           => false,
					'vulnerable_assets' => true,
					'wp_files'          => true,
					'plugin_files'      => true,
					'theme_files'       => true,
					'abandoned'         => false,
				],
				'counts' => [
					'malware'           => 5,
					'vulnerable_assets' => 0,
					'wp_files'          => 0,
					'plugin_files'      => 2,
					'theme_files'       => 0,
					'abandoned'         => 3,
				],
			]
		);

		$rows = $this->invokeNonPublicMethod( new PageOperatorModeLanding(), 'buildActionsQueueRows', [
			[],
		] );

		$this->assertSame(
			[
				'vulnerable_assets',
				'wp_files',
				'plugin_files',
				'theme_files',
				'maintenance',
			],
			\array_column( $rows, 'key' )
		);
		$this->assertSame(
			[
				'vulnerable_assets' => 'good',
				'wp_files'          => 'good',
				'plugin_files'      => 'warning',
				'theme_files'       => 'good',
				'maintenance'       => 'good',
			],
			\array_combine( \array_column( $rows, 'key' ), \array_column( $rows, 'severity' ) )
		);
	}

	public function test_render_data_exposes_actions_queue_title_and_secondary_lanes() :void {
		$this->installControllerStubWithQueuePayload(
			[
				'render_data' => [
					'vars' => [
						'summary' => [
							'has_items'   => true,
							'total_items' => 4,
							'severity'    => 'warning',
							'icon_class'  => 'bi bi-shield-exclamation',
							'subtext'     => 'Latest attention items',
						],
						'zone_groups' => [
							[
								'slug'         => 'maintenance',
								'label'        => 'Maintenance',
								'icon_class'   => 'bi bi-wrench',
								'severity'     => 'warning',
								'total_issues' => 1,
								'items'        => [],
							],
						],
					],
				],
			],
			[
				'enabled' => [
					'malware'           => true,
					'vulnerable_assets' => true,
					'wp_files'          => false,
					'plugin_files'      => false,
					'theme_files'       => false,
					'abandoned'         => false,
				],
				'counts' => [
					'malware'           => 2,
					'vulnerable_assets' => 0,
				],
			]
		);

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
			[ 'malware', 'vulnerable_assets', 'maintenance' ],
			\array_column( $renderData[ 'vars' ][ 'actions_queue_rows' ] ?? [], 'key' )
		);
	}

	private function newPage() :PageOperatorModeLanding {
		return new class extends PageOperatorModeLanding {
			protected function getZonePosture() :array {
				return [
					'components' => [],
					'signals'    => [],
					'totals'     => [
						'score'       => 72,
						'max_weight'  => 100,
						'percentage'  => 72,
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
		};
	}

	private function installControllerStubWithQueuePayload( array $queuePayload, array $scanState = [] ) :void {
		$this->scanState = \array_replace_recursive( $this->defaultScanState(), $scanState );

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
		$controller->comps = (object)[
			'scans' => new class( $this->scanState ) {
				/** @var array{enabled:array<string,bool>,counts:array<string,int>,reports_count:int,latest_report_at:int,latest_alert_at:int} */
				private array $scanState;

				public function __construct( array $scanState ) {
					$this->scanState = $scanState;
				}

				public function getScanResultsCount() :object {
					return new class( $this->scanState[ 'counts' ] ) {
						/** @var array<string,int> */
						private array $counts;

						public function __construct( array $counts ) {
							$this->counts = $counts;
						}

						public function countMalware() :int {
							return (int)( $this->counts[ 'malware' ] ?? 0 );
						}

						public function countVulnerableAssets() :int {
							return (int)( $this->counts[ 'vulnerable_assets' ] ?? 0 );
						}

						public function countWPFiles() :int {
							return (int)( $this->counts[ 'wp_files' ] ?? 0 );
						}

						public function countPluginFiles() :int {
							return (int)( $this->counts[ 'plugin_files' ] ?? 0 );
						}

						public function countThemeFiles() :int {
							return (int)( $this->counts[ 'theme_files' ] ?? 0 );
						}

						public function countAbandoned() :int {
							return (int)( $this->counts[ 'abandoned' ] ?? 0 );
						}
					};
				}

				public function AFS() :object {
					return new class( $this->scanState[ 'enabled' ] ) {
						/** @var array<string,bool> */
						private array $enabled;

						public function __construct( array $enabled ) {
							$this->enabled = $enabled;
						}

						public function isEnabledMalwareScanPHP() :bool {
							return (bool)( $this->enabled[ 'malware' ] ?? false );
						}

						public function isScanEnabledWpCore() :bool {
							return (bool)( $this->enabled[ 'wp_files' ] ?? false );
						}

						public function isScanEnabledPlugins() :bool {
							return (bool)( $this->enabled[ 'plugin_files' ] ?? false );
						}

						public function isScanEnabledThemes() :bool {
							return (bool)( $this->enabled[ 'theme_files' ] ?? false );
						}
					};
				}

				public function WPV() :object {
					return new class( $this->scanState[ 'enabled' ] ) {
						/** @var array<string,bool> */
						private array $enabled;

						public function __construct( array $enabled ) {
							$this->enabled = $enabled;
						}

						public function isEnabled() :bool {
							return (bool)( $this->enabled[ 'vulnerable_assets' ] ?? false );
						}
					};
				}

				public function APC() :object {
					return new class( $this->scanState[ 'enabled' ] ) {
						/** @var array<string,bool> */
						private array $enabled;

						public function __construct( array $enabled ) {
							$this->enabled = $enabled;
						}

						public function isEnabled() :bool {
							return (bool)( $this->enabled[ 'abandoned' ] ?? false );
						}
					};
				}
			},
		];
		$controller->db_con = (object)[
			'reports' => new class( $this->scanState[ 'reports_count' ], $this->scanState[ 'latest_report_at' ], $this->scanState[ 'latest_alert_at' ] ) {
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
		$controller->action_router = new class( $queuePayload ) {
			private array $queuePayload;

			public function __construct( array $queuePayload ) {
				$this->queuePayload = $queuePayload;
			}

			public function action( string $class ) :object {
				return new class( $this->queuePayload ) {
					private array $queuePayload;

					public function __construct( array $queuePayload ) {
						$this->queuePayload = $queuePayload;
					}

					public function payload() :array {
						return $this->queuePayload;
					}
				};
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	/**
	 * @return array{enabled:array<string,bool>,counts:array<string,int>,reports_count:int,latest_report_at:int,latest_alert_at:int}
	 */
	private function defaultScanState() :array {
		return [
			'enabled' => [
				'malware'           => false,
				'vulnerable_assets' => false,
				'wp_files'          => false,
				'plugin_files'      => false,
				'theme_files'       => false,
				'abandoned'         => false,
			],
			'counts' => [
				'malware'           => 0,
				'vulnerable_assets' => 0,
				'wp_files'          => 0,
				'plugin_files'      => 0,
				'theme_files'       => 0,
				'abandoned'         => 0,
			],
			'reports_count'    => 0,
			'latest_report_at' => 0,
			'latest_alert_at'  => 0,
		];
	}
}
