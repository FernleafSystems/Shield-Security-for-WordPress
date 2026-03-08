<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueue;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	PageActionsQueueLanding,
	PageScansResults
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Plugins,
	Request,
	Themes,
	Users
};

class PageActionsQueueLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private object $capture;
	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		Functions\when( 'sanitize_text_field' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \trim( $text ) : ''
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map(
						static fn( $item ) :string => \rawurlencode( (string)$item ),
						$value
					);
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				if ( empty( $params ) ) {
					return $url;
				}
				$pieces = [];
				foreach ( $params as $key => $value ) {
					$pieces[] = $key.'='.$value;
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installServices();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_mode_shell_contract_is_interactive_with_two_tiles() :void {
		$this->capture->queuePayload = $this->buildQueuePayload(
			true,
			5,
			'critical',
			'Last scan: 2 minutes ago',
			[
				$this->buildZoneGroup( 'scans', 'critical', 3, [
					$this->buildQueueItem( 'malware', 'scans', 'Malware', 2, 'critical' ),
					$this->buildQueueItem( 'vulnerabilities', 'scans', 'Vulnerabilities', 1, 'warning' ),
				] ),
				$this->buildZoneGroup( 'maintenance', 'warning', 2, [
					$this->buildQueueItem( 'wp_updates', 'maintenance', 'WordPress Version', 2, 'warning' ),
				] ),
			]
		);

		$page = $this->newPage();
		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$modeShell = (array)( $renderData[ 'vars' ][ 'mode_shell' ] ?? [] );
		$modeTiles = (array)( $renderData[ 'vars' ][ 'mode_tiles' ] ?? [] );
		$modePanel = (array)( $renderData[ 'vars' ][ 'mode_panel' ] ?? [] );

		$this->assertSame( 'actions', $modeShell[ 'mode' ] ?? '' );
		$this->assertSame( 'critical', $modeShell[ 'accent_status' ] ?? '' );
		$this->assertTrue( (bool)( $modeShell[ 'is_interactive' ] ?? false ) );
		$this->assertCount( 2, $modeTiles );
		$this->assertEqualsCanonicalizing( [ 'scans', 'maintenance' ], \array_column( $modeTiles, 'key' ) );
		$this->assertSame( '', $modePanel[ 'active_target' ] ?? 'missing' );
		$this->assertFalse( (bool)( $modePanel[ 'is_open' ] ?? true ) );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'queue_is_empty' ] ?? true ) );
	}

	public function test_landing_panel_accepts_issue_zone_and_clear_zone_with_panel_content() :void {
		$this->capture->queuePayload = $this->buildQueuePayload(
			true,
			2,
			'warning',
			'',
			[
				$this->buildZoneGroup( 'scans', 'warning', 2, [
					$this->buildQueueItem( 'malware', 'scans', 'Malware', 2, 'warning' ),
				] ),
				$this->buildZoneGroup( 'maintenance', 'good', 0, [] ),
			]
		);

		$pageAllowed = $this->newPage( null, [ 'zone' => 'scans' ] );
		$this->assertSame(
			'scans',
			$this->invokeNonPublicMethod( $pageAllowed, 'getLandingPanel' )[ 'active_target' ] ?? ''
		);

		$pageClear = $this->newPage( null, [ 'zone' => 'maintenance' ] );
		$this->assertSame(
			'maintenance',
			$this->invokeNonPublicMethod( $pageClear, 'getLandingPanel' )[ 'active_target' ] ?? ''
		);

		$pageBlocked = $this->newPage( null, [ 'zone' => 'unknown' ] );
		$this->assertSame(
			'',
			$this->invokeNonPublicMethod( $pageBlocked, 'getLandingPanel' )[ 'active_target' ] ?? 'unexpected'
		);
	}

	public function test_landing_vars_expose_severity_strip_zone_tiles_and_scan_results_contract() :void {
		$this->capture->queuePayload = $this->buildQueuePayload(
			true,
			5,
			'critical',
			'Last scan: 2 minutes ago',
			[
				$this->buildZoneGroup( 'scans', 'critical', 3, [
					$this->buildQueueItem( 'malware', 'scans', 'Malware', 2, 'critical' ),
					$this->buildQueueItem( 'vulnerabilities', 'scans', 'Vulnerabilities', 1, 'warning' ),
				] ),
				$this->buildZoneGroup( 'maintenance', 'warning', 2, [
					$this->buildQueueItem( 'wp_updates', 'maintenance', 'WordPress Version', 2, 'warning' ),
				] ),
			]
		);

		$page = $this->newPage();
		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$strip = (array)( $vars[ 'severity_strip' ] ?? [] );
		$allClear = (array)( $vars[ 'all_clear' ] ?? [] );

		$this->assertSame( 'critical', $strip[ 'severity' ] ?? '' );
		$this->assertSame( 5, $strip[ 'total_items' ] ?? null );
		$this->assertSame( 2, $strip[ 'critical_count' ] ?? null );
		$this->assertSame( 3, $strip[ 'warning_count' ] ?? null );
		$this->assertSame( 'Last scan: 2 minutes ago', $strip[ 'subtext' ] ?? '' );

		$this->assertCount( 2, $vars[ 'zone_tiles' ] ?? [] );
		$this->assertEqualsCanonicalizing(
			[ 'scans', 'maintenance' ],
			\array_column( (array)( $vars[ 'zone_tiles' ] ?? [] ), 'key' )
		);

		$this->assertEqualsCanonicalizing(
			[ 'scans', 'maintenance' ],
			\array_column( (array)( $allClear[ 'zone_chips' ] ?? [] ), 'slug' )
		);
		$this->assertSame(
			$this->capture->scansResultsPayload[ 'render_data' ],
			$vars[ 'scans_results' ] ?? []
		);
		$this->assertSame( 0, (int)( $vars[ 'scans_vulnerabilities' ][ 'count' ] ?? -1 ) );
		$this->assertSame( 'good', (string)( $vars[ 'scans_vulnerabilities' ][ 'status' ] ?? '' ) );

		$scanResultsCalls = \array_values( \array_filter(
			$this->capture->actionCalls,
			static fn( array $call ) :bool => ( $call[ 'action' ] ?? '' ) === PageScansResults::class
		) );
		$this->assertCount( 1, $scanResultsCalls );
		$this->assertSame(
			PluginNavs::NAV_SCANS,
			$scanResultsCalls[ 0 ][ 'action_data' ][ Constants::NAV_ID ] ?? ''
		);
		$this->assertSame(
			PluginNavs::SUBNAV_SCANS_RESULTS,
			$scanResultsCalls[ 0 ][ 'action_data' ][ Constants::NAV_SUB_ID ] ?? ''
		);
	}

	public function test_scans_results_payload_is_not_loaded_when_scans_zone_has_no_items() :void {
		$this->capture->queuePayload = $this->buildQueuePayload(
			true,
			1,
			'warning',
			'',
			[
				$this->buildZoneGroup( 'scans', 'good', 0, [] ),
				$this->buildZoneGroup( 'maintenance', 'warning', 1, [
					$this->buildQueueItem( 'wp_updates', 'maintenance', 'WordPress Version', 1, 'warning' ),
				] ),
			]
		);

		$page = $this->newPage();
		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$this->assertSame( [], $vars[ 'scans_results' ] ?? null );
		$this->assertSame( [], $vars[ 'scans_vulnerabilities' ][ 'items' ] ?? null );

		$scanResultsCalls = \array_values( \array_filter(
			$this->capture->actionCalls,
			static fn( array $call ) :bool => ( $call[ 'action' ] ?? '' ) === PageScansResults::class
		) );
		$this->assertCount( 0, $scanResultsCalls );
	}

	public function test_landing_hrefs_reuse_existing_scan_and_wp_admin_routes() :void {
		$page = $this->newPage();
		$hrefs = $this->invokeNonPublicMethod( $page, 'getLandingHrefs' );

		$this->assertSame(
			[
				'scan_results' => '/admin/scans/results',
				'wp_updates'   => '/wp-admin/update-core.php',
			],
			$hrefs
		);
	}

	public function test_maintenance_items_get_expected_row_ctas_for_internal_and_external_actions() :void {
		$this->capture->queuePayload = $this->buildQueuePayload(
			true,
			4,
			'warning',
			'',
			[
				$this->buildZoneGroup( 'scans', 'good', 0, [] ),
				$this->buildZoneGroup( 'maintenance', 'warning', 2, [
					$this->buildQueueItem( 'wp_plugins_inactive', 'maintenance', 'Inactive Plugins', 1, 'warning' ),
					$this->buildQueueItem( 'wp_themes_inactive', 'maintenance', 'Inactive Themes', 1, 'warning' ),
					$this->buildQueueItem( 'wp_updates', 'maintenance', 'WordPress Version', 1, 'warning', '_blank' ),
					[
						'key'         => 'system_lib_openssl',
						'zone'        => 'maintenance',
						'label'       => 'OpenSSL Extension',
						'count'       => 1,
						'severity'    => 'warning',
						'description' => 'OpenSSL requires review.',
						'href'        => 'https://www.openssl.org/news/vulnerabilities.html',
						'action'      => 'Review',
						'target'      => '_blank',
					],
				] ),
			]
		);

		$page = $this->newPage();
		$zoneTiles = $this->invokeNonPublicMethod( $page, 'getLandingVars' )[ 'zone_tiles' ] ?? [];
		$maintenance = \array_values( \array_filter(
			$zoneTiles,
			static fn( array $tile ) :bool => ( $tile[ 'key' ] ?? '' ) === 'maintenance'
		) )[ 0 ] ?? [];
		$itemsByKey = [];
		foreach ( $maintenance[ 'items' ] ?? [] as $item ) {
			$itemsByKey[ $item[ 'key' ] ?? '' ] = $item;
		}

		$this->assertNotEmpty( $maintenance[ 'assessment_rows' ] ?? [] );
		$this->assertSame( 'Go to plugins', $itemsByKey[ 'wp_plugins_inactive' ][ 'cta' ][ 'label' ] ?? '' );
		$this->assertSame( '/admin/wp_plugins_inactive', $itemsByKey[ 'wp_plugins_inactive' ][ 'cta' ][ 'href' ] ?? '' );
		$this->assertSame( 'Go to themes', $itemsByKey[ 'wp_themes_inactive' ][ 'cta' ][ 'label' ] ?? '' );
		$this->assertSame( '/admin/wp_themes_inactive', $itemsByKey[ 'wp_themes_inactive' ][ 'cta' ][ 'href' ] ?? '' );
		$this->assertSame( 'open', $itemsByKey[ 'wp_updates' ][ 'cta' ][ 'label' ] ?? '' );
		$this->assertSame( '/admin/wp_updates', $itemsByKey[ 'wp_updates' ][ 'cta' ][ 'href' ] ?? '' );
		$this->assertSame( '_blank', $itemsByKey[ 'wp_updates' ][ 'cta' ][ 'target' ] ?? '' );
		$this->assertSame( 'Review', $itemsByKey[ 'system_lib_openssl' ][ 'cta' ][ 'label' ] ?? '' );
		$this->assertSame( 'https://www.openssl.org/news/vulnerabilities.html', $itemsByKey[ 'system_lib_openssl' ][ 'cta' ][ 'href' ] ?? '' );
		$this->assertSame( '_blank', $itemsByKey[ 'system_lib_openssl' ][ 'cta' ][ 'target' ] ?? '' );
	}

	public function test_maintenance_sections_group_items_without_repeating_warnings() :void {
		$this->capture->queuePayload = $this->buildQueuePayload(
			true,
			3,
			'critical',
			'',
			[
				$this->buildZoneGroup( 'scans', 'good', 0, [] ),
				$this->buildZoneGroup( 'maintenance', 'critical', 3, [
					$this->buildQueueItem( 'system_ssl_certificate', 'maintenance', 'SSL Certificate', 1, 'critical' ),
					$this->buildQueueItem( 'wp_updates', 'maintenance', 'WordPress Version', 1, 'warning' ),
				] ),
			]
		);

		$page = $this->newPage( [
			'scans'       => [
				$this->buildAssessmentRow( 'wp_files', 'WordPress Core Files' ),
			],
			'maintenance' => [
				$this->buildAssessmentRow( 'system_ssl_certificate', 'SSL Certificate', 'Certificate requires review', 'critical', 'Critical', 'bi bi-x-circle-fill' ),
				$this->buildAssessmentRow( 'wp_updates', 'WordPress Version', 'Update available', 'warning', 'Warning', 'bi bi-exclamation-circle-fill' ),
				$this->buildAssessmentRow( 'system_lib_openssl', 'OpenSSL Extension' ),
			],
		] );
		$zoneTiles = $this->invokeNonPublicMethod( $page, 'getLandingVars' )[ 'zone_tiles' ] ?? [];
		$maintenance = \array_values( \array_filter(
			$zoneTiles,
			static fn( array $tile ) :bool => ( $tile[ 'key' ] ?? '' ) === 'maintenance'
		) )[ 0 ] ?? [];
		$sections = $maintenance[ 'maintenance_sections' ] ?? [];

		$this->assertSame( [ 'critical', 'warning', 'good' ], \array_column( $sections, 'key' ) );
		$this->assertSame( [ 'summary', 'summary', 'assessment' ], \array_column( $sections, 'kind' ) );
		$this->assertSame( [ 'system_ssl_certificate' ], \array_column( $sections[ 0 ][ 'items' ] ?? [], 'key' ) );
		$this->assertSame( [ 'wp_updates' ], \array_column( $sections[ 1 ][ 'items' ] ?? [], 'key' ) );
		$this->assertSame( [ 'system_lib_openssl' ], \array_column( $sections[ 2 ][ 'items' ] ?? [], 'key' ) );
	}

	public function test_missing_needs_attention_strings_fall_back_to_safe_defaults() :void {
		$payload = $this->buildQueuePayload( false, 0, 'good', '', [] );
		$payload[ 'render_data' ][ 'strings' ] = [];
		$this->capture->queuePayload = $payload;

		$page = $this->newPage();
		$strings = $this->invokeNonPublicMethod( $page, 'getLandingStrings' );
		$allClear = $this->invokeNonPublicMethod( $page, 'getLandingVars' )[ 'all_clear' ] ?? [];

		$this->assertSame( 'All security zones are clear', $strings[ 'all_clear_title' ] ?? '' );
		$this->assertSame(
			'Shield is actively protecting your site. Nothing requires your action.',
			$strings[ 'all_clear_subtitle' ] ?? ''
		);
		$this->assertSame( 'bi bi-shield-check', $strings[ 'all_clear_icon_class' ] ?? '' );
		$this->assertSame( 'All security zones are clear', $allClear[ 'title' ] ?? '' );
		$this->assertSame( 'bi bi-shield-check', $allClear[ 'icon_class' ] ?? '' );
	}

	public function test_queue_and_scan_results_payloads_are_cached_per_page_instance() :void {
		$this->capture->queuePayload = $this->buildQueuePayload(
			true,
			3,
			'warning',
			'Last scan: 4 minutes ago',
			[
				$this->buildZoneGroup( 'scans', 'warning', 2, [
					$this->buildQueueItem( 'malware', 'scans', 'Malware', 2, 'warning' ),
				] ),
				$this->buildZoneGroup( 'maintenance', 'warning', 1, [
					$this->buildQueueItem( 'wp_updates', 'maintenance', 'WordPress Version', 1, 'warning' ),
				] ),
			]
		);

		$page = $this->newPage();
		$this->invokeNonPublicMethod( $page, 'getLandingFlags' );
		$this->invokeNonPublicMethod( $page, 'getLandingStrings' );
		$this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$queueCalls = \array_values( \array_filter(
			$this->capture->actionCalls,
			static fn( array $call ) :bool => ( $call[ 'action' ] ?? '' ) === NeedsAttentionQueue::class
		) );
		$scanResultsCalls = \array_values( \array_filter(
			$this->capture->actionCalls,
			static fn( array $call ) :bool => ( $call[ 'action' ] ?? '' ) === PageScansResults::class
		) );

		$this->assertCount( 1, $queueCalls );
		$this->assertSame( [ 'compact_all_clear' => true ], $queueCalls[ 0 ][ 'action_data' ] ?? [] );
		$this->assertCount( 1, $scanResultsCalls );
	}

	private function installControllerStub() :void {
		$this->capture = (object)[
			'actionCalls'        => [],
			'queuePayload'       => $this->buildQueuePayload( false, 0, 'good', '', [] ),
			'scansResultsPayload' => [
				'render_data' => [
					'strings'     => [
						'results_tab_wordpress' => 'WordPress',
					],
					'vars'        => [
						'sections' => [],
					],
					'content'     => [
						'section' => [],
					],
					'flags'       => [],
					'hrefs'       => [],
					'imgs'        => [],
					'file_locker' => [],
				],
			],
		];

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
			'scans' => new class {
				public function AFS() :object {
					return new class {
						public function isEnabledMalwareScanPHP() :bool {
							return false;
						}

						public function isScanEnabledWpCore() :bool {
							return false;
						}

						public function isScanEnabledPlugins() :bool {
							return false;
						}

						public function isScanEnabledThemes() :bool {
							return false;
						}
					};
				}

				public function WPV() :object {
					return new class {
						public function isEnabled() :bool {
							return false;
						}
					};
				}

				public function APC() :object {
					return new class {
						public function isEnabled() :bool {
							return false;
						}
					};
				}
			},
		];
		$controller->action_router = new class( $this->capture ) {
			private object $capture;

			public function __construct( object $capture ) {
				$this->capture = $capture;
			}

			public function action( string $action, array $actionData = [] ) :object {
				$this->capture->actionCalls[] = [
					'action'      => $action,
					'action_data' => $actionData,
				];

				$payload = [];
				if ( $action === NeedsAttentionQueue::class ) {
					$payload = $this->capture->queuePayload;
				}
				elseif ( $action === PageScansResults::class ) {
					$payload = $this->capture->scansResultsPayload;
				}

				return new class( $payload ) {
					private array $payload;

					public function __construct( array $payload ) {
						$this->payload = $payload;
					}

					public function payload() :array {
						return $this->payload;
					}
				};
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	/**
	 * @param array<string,list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }>>|null $assessmentRowsByZone
	 */
	private function newPage( ?array $assessmentRowsByZone = null, array $actionData = [] ) :PageActionsQueueLanding {
		$page = new PageActionsQueueLandingUnitTestDouble(
			$assessmentRowsByZone ?? $this->buildDefaultAssessmentRowsByZone()
		);
		$page->action_data = $actionData;
		return $page;
	}

	private function installServices( array $query = [] ) :void {
		ServicesState::installItems( [
			'service_request' => new class( $query ) extends Request {
				private array $queryValues;

				public function __construct( array $queryValues = [] ) {
					$this->queryValues = $queryValues;
				}

				public function query( $key, $default = null ) {
					return $this->queryValues[ $key ] ?? $default;
				}

				public function ip() :string {
					return '127.0.0.1';
				}

				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
			'service_wpgeneral' => new class extends General {
				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}

				public function hasCoreUpdate() :bool {
					return false;
				}

				public function getAdminUrl_Updates( bool $bWpmsOnly = false ) :string {
					return '/wp-admin/update-core.php';
				}

				public function getAdminUrl_Plugins( bool $wpmsOnly = false ) :string {
					return '/wp-admin/plugins.php';
				}

				public function getAdminUrl_Themes( bool $wpmsOnly = false ) :string {
					return '/wp-admin/themes.php';
				}
			},
			'service_wpplugins' => new class extends Plugins {
				public function getUpdates( $bForceUpdateCheck = false ) {
					return [];
				}
			},
			'service_wpthemes' => new class extends Themes {
				public function getUpdates( $forceCheck = false ) {
					return [];
				}
			},
			'service_wpusers' => new class extends Users {
				public function getCurrentWpUserId() {
					return 1;
				}
			},
		] );
	}

	/**
	 * @return array<string,list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }>>
	 */
	private function buildDefaultAssessmentRowsByZone() :array {
		return [
			'scans'       => [
				$this->buildAssessmentRow( 'wp_files', 'WordPress Core Files' ),
			],
			'maintenance' => [
				$this->buildAssessmentRow( 'wp_updates', 'WordPress Version' ),
			],
		];
	}

	/**
	 * @return array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:'good',
	 *   status_label:'Good',
	 *   status_icon_class:'bi bi-check-circle-fill'
	 * }
	 */
	private function buildAssessmentRow(
		string $key,
		string $label,
		string $description = 'All clear',
		string $status = 'good',
		string $statusLabel = 'Good',
		string $statusIconClass = 'bi bi-check-circle-fill'
	) :array {
		return [
			'key'               => $key,
			'label'             => $label,
			'description'       => $description,
			'status'            => $status,
			'status_label'      => $statusLabel,
			'status_icon_class' => $statusIconClass,
		];
	}

	/**
	 * @param list<array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array{
	 *     key:string,
	 *     zone:string,
	 *     label:string,
	 *     count:int,
	 *     severity:string,
	 *     description:string,
	 *     href:string,
	 *     action:string
	 *   }>
	 * }> $zoneGroups
	 */
	private function buildQueuePayload(
		bool $hasItems,
		int $totalItems,
		string $severity,
		string $subtext,
		array $zoneGroups
	) :array {
		return [
			'render_output' => 'rendered-needs-attention-queue',
			'render_data'   => [
				'flags'   => [
					'has_items' => $hasItems,
				],
				'strings' => [
					'all_clear_title'      => 'All security zones are clear',
					'all_clear_subtitle'   => 'Shield is actively protecting your site. Nothing requires your action.',
					'status_strip_subtext' => $subtext,
					'all_clear_icon_class' => 'bi bi-shield-check',
				],
				'vars'    => [
					'summary'     => [
						'has_items'   => $hasItems,
						'total_items' => $totalItems,
						'severity'    => $severity,
						'icon_class'  => 'bi bi-from-summary',
						'subtext'     => $subtext,
					],
					'zone_groups' => $zoneGroups,
				],
			],
		];
	}

	/**
	 * @param list<array{
	 *   key:string,
	 *   zone:string,
	 *   label:string,
	 *   count:int,
	 *   severity:string,
	 *   description:string,
	 *   href:string,
	 *   action:string
	 * }> $items
	 * @return array{
	 *   slug:string,
	 *   label:string,
	 *   icon_class:string,
	 *   severity:string,
	 *   total_issues:int,
	 *   items:list<array{
	 *     key:string,
	 *     zone:string,
	 *     label:string,
	 *     count:int,
	 *     severity:string,
	 *     description:string,
	 *     href:string,
	 *     action:string
	 *   }>
	 * }
	 */
	private function buildZoneGroup( string $slug, string $severity, int $totalIssues, array $items ) :array {
		return [
			'slug'         => $slug,
			'label'        => $slug === 'maintenance' ? 'Maintenance' : 'Scans',
			'icon_class'   => 'bi bi-'.$slug,
			'severity'     => $severity,
			'total_issues' => $totalIssues,
			'items'        => $items,
		];
	}

	/**
	 * @return array{
	 *   key:string,
	 *   zone:string,
	 *   label:string,
	 *   count:int,
	 *   severity:string,
	 *   description:string,
	 *   href:string,
	 *   action:string
	 * }
	 */
	private function buildQueueItem(
		string $key,
		string $zone,
		string $label,
		int $count,
		string $severity,
		string $target = ''
	) :array {
		return [
			'key'         => $key,
			'zone'        => $zone,
			'label'       => $label,
			'count'       => $count,
			'severity'    => $severity,
			'description' => 'Description for '.$label,
			'href'        => '/admin/'.$key,
			'action'      => 'open',
			'target'      => $target,
		];
	}
}

class PageActionsQueueLandingUnitTestDouble extends PageActionsQueueLanding {

	/**
	 * @param array<string,list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }>> $assessmentRowsByZone
	 */
	public function __construct( private array $assessmentRowsByZone ) {
	}

	protected function buildAssessmentRowsByZone() :array {
		return $this->assessmentRowsByZone;
	}
}
