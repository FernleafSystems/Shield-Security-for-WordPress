<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueDrillDownGroups,
	DetailExpansionType,
	PageActionsQueueLanding
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	MaintenanceAssetFixtures,
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestLicenseComponent,
	UnitTestOptionsComponent,
	UnitTestPluginUrls,
	UnitTestRequest,
	UnitTestScansComponent,
	UnitTestUsers
};

class PageActionsQueueLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;
	use MaintenanceAssetFixtures;

	private object $capture;
	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		if ( !\defined( 'DB_PASSWORD' ) ) {
			\define( 'DB_PASSWORD', 'correct-horse-battery-staple' );
		}
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
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = 'auth' ) :string => 'hash-'.$scheme.'-'.$data
		);
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
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

	public function test_render_data_contains_drill_shell_and_ajax_contracts() :void {
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
		$vars = $renderData[ 'vars' ];

		$this->assertSame( 'actions', $vars[ 'mode_shell' ][ 'mode' ] );
		$this->assertFalse( $vars[ 'mode_shell' ][ 'is_interactive' ] );
		$this->assertTrue( (bool)( $vars[ 'mode_shell' ][ 'use_operator_chrome' ] ?? false ) );
		$this->assertSame( '/admin/home', $vars[ 'mode_shell' ][ 'home_href' ] ?? '' );
		$this->assertSame( 'Actions Queue', $vars[ 'mode_shell' ][ 'root_step' ][ 'title' ] ?? '' );
		$this->assertSame( '5 items', $vars[ 'mode_shell' ][ 'root_step' ][ 'badge' ] ?? '' );
		$this->assertSame( 'Last scan: 2 minutes ago', $vars[ 'mode_shell' ][ 'root_step' ][ 'focus' ] ?? '' );
		$this->assertSame( 'actions', $vars[ 'mode_shell' ][ 'root_step' ][ 'color_key' ] ?? '' );
		$this->assertSame( 'actions_drill_shell', $vars[ 'drill_shell' ][ 'id' ] );
		$this->assertSame( 0, $vars[ 'drill_shell' ][ 'active_index' ] );
		$this->assertSame( [ 'buckets', 'groups', 'detail' ], \array_column( $vars[ 'drill_shell' ][ 'layers' ], 'key' ) );
		$this->assertSame( '__BUCKETS_LAYER__', $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'body' ] );
		$this->assertSame( 'Grouped findings', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'title' ] );
		$this->assertSame( 'Select', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'badge' ] );
		$this->assertSame( 'Back to Actions Queue', $vars[ 'drill_shell' ][ 'layers' ][ 0 ][ 'header' ][ 'compact_back_label' ] );
		$this->assertSame(
			'Choose a bucket to start.',
			$vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'summary' ]
		);
		$this->assertSame( 'Grouped findings', $vars[ 'drill_shell' ][ 'layers' ][ 1 ][ 'header' ][ 'breadcrumb_label' ] ?? '' );
		$this->assertArrayNotHasKey( 'drill_context_card', $vars );
		$this->assertCount( 2, $vars[ 'zone_tiles' ] );
		$this->assertArrayNotHasKey( 'groups_render_action', $vars[ 'actions_queue_ajax' ] );
		$this->assertSame(
			ActionsQueueDrillDownGroups::SLUG,
			\json_decode( $vars[ 'actions_queue_ajax' ][ 'groups_render_action_json' ] ?? '', true )[ 'render_slug' ] ?? ''
		);
		$this->assertSame( 'Loading grouped findings...', $renderData[ 'strings' ][ 'groups_loading' ] );
		$this->assertSame( 'Loading scoped results...', $renderData[ 'strings' ][ 'detail_loading' ] );
		$this->assertArrayNotHasKey( 'scans_results', $vars );
		$this->assertFalse( $renderData[ 'flags' ][ 'queue_is_empty' ] );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_drilldown_content' ] ?? false ) );
	}

	public function test_all_clear_flag_follows_attention_summary_without_queue_box_contract() :void {
		$this->capture->queuePayload = $this->buildQueuePayload(
			false,
			0,
			'good',
			'',
			[
				$this->buildZoneGroup( 'scans', 'good', 0, [] ),
				$this->buildZoneGroup( 'maintenance', 'good', 0, [] ),
			]
		);

		$page = $this->newPage();
		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$vars = $renderData[ 'vars' ];

		$this->assertTrue( $renderData[ 'flags' ][ 'queue_is_empty' ] );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_drilldown_content' ] ?? false ) );
		$this->assertSame( 'good', $vars[ 'mode_shell' ][ 'root_step' ][ 'badge_status' ] ?? '' );
		$this->assertNotSame( '', \trim( (string)( $vars[ 'mode_shell' ][ 'root_step' ][ 'badge' ] ?? '' ) ) );
		$this->assertArrayNotHasKey( 'all_clear', $vars );
		$this->assertArrayNotHasKey( 'all_clear_title', $renderData[ 'strings' ] );
	}

	public function test_root_step_json_helper_reuses_the_landing_root_step_contract() :void {
		$this->capture->queuePayload = $this->buildQueuePayload(
			true,
			3,
			'warning',
			'Last scan: 5 minutes ago',
			[
				$this->buildZoneGroup( 'scans', 'warning', 3, [
					$this->buildQueueItem( 'malware', 'scans', 'Malware', 3, 'warning' ),
				] ),
				$this->buildZoneGroup( 'maintenance', 'good', 0, [] ),
			]
		);

		$page = $this->newPage();
		$rootStepJson = $this->invokeNonPublicMethod( $page, 'buildActionsQueueOperatorRootStepJson' );
		$rootStep = \json_decode( $rootStepJson, true );

		$this->assertSame( 'Actions Queue', $rootStep[ 'title' ] ?? '' );
		$this->assertSame( '3 items', $rootStep[ 'badge' ] ?? '' );
		$this->assertSame( 'Last scan: 5 minutes ago', $rootStep[ 'focus' ] ?? '' );
		$this->assertSame( 'actions', $rootStep[ 'color_key' ] ?? '' );
	}

	public function test_landing_hrefs_reuse_existing_scan_and_wp_admin_routes() :void {
		$page = $this->newPage();
		$hrefs = $this->invokeNonPublicMethod( $page, 'getLandingHrefs' );

		$this->assertSame(
			[
				'scan_results' => '/admin/scans/overview?zone=scans',
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

		$zoneTiles = $this->invokeNonPublicMethod( $this->newPage(), 'getLandingVars' )[ 'zone_tiles' ];
		$maintenance = \array_values( \array_filter(
			$zoneTiles,
			static fn( array $tile ) :bool => $tile[ 'key' ] === 'maintenance'
		) )[ 0 ];
		$itemsByKey = [];
		foreach ( $maintenance[ 'items' ] as $item ) {
			$itemsByKey[ $item[ 'key' ] ] = $item;
		}

		$this->assertSame( 'Manage Plugins', $itemsByKey[ 'wp_plugins_inactive' ][ 'cta' ][ 'label' ] );
		$this->assertSame( '/admin/wp_plugins_inactive', $itemsByKey[ 'wp_plugins_inactive' ][ 'cta' ][ 'href' ] );
		$this->assertSame( 'Manage Themes', $itemsByKey[ 'wp_themes_inactive' ][ 'cta' ][ 'label' ] );
		$this->assertSame( '/admin/wp_themes_inactive', $itemsByKey[ 'wp_themes_inactive' ][ 'cta' ][ 'href' ] );
		$this->assertSame( 'Dashboard -> Updates', $itemsByKey[ 'wp_updates' ][ 'cta' ][ 'label' ] );
		$this->assertSame( '/admin/wp_updates', $itemsByKey[ 'wp_updates' ][ 'cta' ][ 'href' ] );
		$this->assertSame( '_blank', $itemsByKey[ 'wp_updates' ][ 'cta' ][ 'target' ] );
		$this->assertSame( [], $itemsByKey[ 'system_lib_openssl' ][ 'cta' ] );
	}

	public function test_maintenance_asset_rows_get_eager_expansion_contracts_in_tile_items() :void {
		$this->installServices(
			[],
			[
				'updates' => [
					'akismet/akismet.php' => [ 'new_version' => '5.4.0' ],
				],
				'plugins' => [
					'akismet/akismet.php'   => [],
					'hello-dolly/hello.php' => [],
				],
				'active' => [
					'akismet/akismet.php',
				],
				'plugin_vos' => [
					'akismet/akismet.php'   => $this->buildMaintenancePluginVo( 'akismet/akismet.php', 'Akismet Anti-Spam', '5.3.0' ),
					'hello-dolly/hello.php' => $this->buildMaintenancePluginVo( 'hello-dolly/hello.php', 'Hello Dolly', '1.7.2' ),
				],
				'activate_urls' => [
					'hello-dolly/hello.php' => '/wp-admin/plugins.php?action=activate&plugin=hello-dolly/hello.php',
				],
				'upgrade_urls' => [
					'akismet/akismet.php' => '/wp-admin/update.php?action=upgrade-plugin&plugin=akismet/akismet.php',
				],
			],
			[
				'updates' => [
					'twentytwentyfive' => [ 'new_version' => '1.2' ],
				],
				'themes' => [
					'twentytwentyfive' => [],
					'inactive-theme'   => [],
				],
				'theme_vos' => [
					'twentytwentyfive' => $this->buildMaintenanceThemeVo( 'twentytwentyfive', 'Twenty Twenty-Five', '1.1' ),
					'inactive-theme'   => $this->buildMaintenanceThemeVo( 'inactive-theme', 'Inactive Theme', '3.0.1' ),
				],
				'current' => 'twentytwentyfive',
			]
		);

		$this->capture->queuePayload = $this->buildQueuePayload(
			true,
			5,
			'warning',
			'',
			[
				$this->buildZoneGroup( 'scans', 'good', 0, [] ),
				$this->buildZoneGroup( 'maintenance', 'warning', 5, [
					$this->buildQueueItem( 'wp_plugins_updates', 'maintenance', 'Plugins With Updates', 1, 'warning' ),
					$this->buildQueueItem( 'wp_themes_updates', 'maintenance', 'Themes With Updates', 1, 'warning' ),
					$this->buildQueueItem( 'wp_plugins_inactive', 'maintenance', 'Inactive Plugins', 1, 'warning' ),
					$this->buildQueueItem( 'wp_themes_inactive', 'maintenance', 'Inactive Themes', 1, 'warning' ),
					$this->buildQueueItem( 'wp_updates', 'maintenance', 'WordPress Version', 1, 'warning' ),
				] ),
			]
		);

		$vars = $this->invokeNonPublicMethod( $this->newPage(), 'getLandingVars' );
		$maintenanceTile = \array_values( \array_filter(
			$vars[ 'zone_tiles' ],
			static fn( array $tile ) :bool => $tile[ 'key' ] === 'maintenance'
		) )[ 0 ];
		$itemsByKey = [];
		foreach ( $maintenanceTile[ 'items' ] as $item ) {
			$itemsByKey[ $item[ 'key' ] ] = $item;
		}

		foreach ( [ 'wp_plugins_updates', 'wp_themes_updates', 'wp_plugins_inactive', 'wp_themes_inactive' ] as $key ) {
			$this->assertNotEmpty( $itemsByKey[ $key ][ 'expansion' ], 'Expected expansion for '.$key );
			$this->assertSame( DetailExpansionType::SIMPLE_TABLE, $itemsByKey[ $key ][ 'expansion' ][ 'type' ] );
		}
		$this->assertSame(
			'/wp-admin/plugins.php?s=hello-dolly%2Fhello.php',
			$itemsByKey[ 'wp_plugins_inactive' ][ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'href' ]
		);
		$this->assertSame( [], $itemsByKey[ 'wp_updates' ][ 'expansion' ] );
	}

	public function test_maintenance_detail_groups_order_rows_without_repeating_problem_assessments() :void {
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
				$this->buildAssessmentRow( 'wp_files', 'WordPress Files', 'All clear', 'good', 'Good', 'bi bi-check-circle-fill', 'critical' ),
			],
			'maintenance' => [
				$this->buildAssessmentRow( 'system_ssl_certificate', 'SSL Certificate', 'Certificate requires review', 'critical', 'Critical', 'bi bi-x-circle-fill', 'review' ),
				$this->buildAssessmentRow( 'wp_updates', 'WordPress Version', 'Update available', 'warning', 'Warning', 'bi bi-exclamation-circle-fill', 'review' ),
				$this->buildAssessmentRow( 'system_lib_openssl', 'OpenSSL Extension', 'All clear', 'good', 'Good', 'bi bi-check-circle-fill', 'review' ),
			],
		] );
		$zoneTiles = $this->invokeNonPublicMethod( $page, 'getLandingVars' )[ 'zone_tiles' ];
		$maintenance = \array_values( \array_filter(
			$zoneTiles,
			static fn( array $tile ) :bool => $tile[ 'key' ] === 'maintenance'
		) )[ 0 ];
		$groups = $maintenance[ 'maintenance_detail_groups' ];

		$this->assertSame( [ 'warning', 'good' ], \array_column( $groups, 'status' ) );
		$this->assertSame( [ 'system_ssl_certificate' ], \array_column( $groups[ 0 ][ 'rows' ], 'key' ) );
		$this->assertSame( [ 'wp_updates', 'system_lib_openssl' ], \array_column( $groups[ 1 ][ 'rows' ], 'key' ) );
	}

	public function test_queue_payload_is_cached_per_page_instance() :void {
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

		$this->assertSame( 0, \count( $this->capture->actionCalls ) );
	}

	private function installControllerStub() :void {
		$this->capture = (object)[
			'actionCalls'  => [],
			'queuePayload' => $this->buildQueuePayload( false, 0, 'good', '', [] ),
		];
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'opts'          => new UnitTestOptionsComponent( [
					'ignored_maintenance_items' => \array_fill_keys( [
						'default_admin_user',
						'wp_plugins_updates',
						'wp_themes_updates',
						'wp_plugins_inactive',
						'wp_themes_inactive',
						'wp_updates',
						'system_ssl_certificate',
						'system_php_version',
						'wp_db_password',
						'system_lib_openssl',
					], [] ),
				] ),
				'comps'         => (object)[
					'scans'   => new UnitTestScansComponent(),
					'license' => new UnitTestLicenseComponent( false ),
				],
				'action_router' => new PageActionsQueueActionRouter( $this->capture ),
			]
		);
	}

	/**
	 * @param array{
	 *   scans:list<array{
	 *     key:string,
	 *     label:string,
	 *     description:string,
	 *     drill_bucket:'critical'|'review',
	 *     item_icon_class:string,
	 *     status:string,
	 *     status_label:string,
	 *     status_icon_class:string
	 *   }>,
	 *   maintenance:list<array{
	 *     key:string,
	 *     label:string,
	 *     description:string,
	 *     drill_bucket:'critical'|'review',
	 *     item_icon_class:string,
	 *     status:string,
	 *     status_label:string,
	 *     status_icon_class:string
	 *   }>
	 * }|null $assessmentRowsByZone
	 */
	private function newPage( ?array $assessmentRowsByZone = null, array $actionData = [] ) :PageActionsQueueLanding {
		$page = new PageActionsQueueLandingUnitTestDouble(
			$assessmentRowsByZone ?? $this->buildDefaultAssessmentRowsByZone(),
			$this->capture->queuePayload
		);
		$page->action_data = $actionData;
		return $page;
	}

	private function installServices( array $query = [], array $pluginFixture = [], array $themeFixture = [] ) :void {
		$assetServices = $this->buildMaintenanceAssetServiceItems( $pluginFixture, $themeFixture );
		unset( $assetServices[ 'service_wpgeneral' ] );

		ServicesState::installItems( \array_merge( [
			'service_request'   => new UnitTestRequest( $query ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers( 1 ),
		], $assetServices ) );
	}

	/**
	 * @return array<string,list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   drill_bucket:'critical'|'review',
	 *   item_icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }>>
	 */
	private function buildDefaultAssessmentRowsByZone() :array {
		return [
			'scans'       => [
				$this->buildAssessmentRow( 'wp_files', 'WordPress Files', 'All clear', 'good', 'Good', 'bi bi-check-circle-fill', 'critical' ),
			],
			'maintenance' => [
				$this->buildAssessmentRow( 'wp_updates', 'WordPress Version', 'All clear', 'good', 'Good', 'bi bi-check-circle-fill', 'review' ),
			],
		];
	}

	/**
	 * @return array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   drill_bucket:'critical'|'review',
	 *   item_icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 * }
	 */
	private function buildAssessmentRow(
		string $key,
		string $label,
		string $description = 'All clear',
		string $status = 'good',
		string $statusLabel = 'Good',
		string $statusIconClass = 'bi bi-check-circle-fill',
		string $drillBucket = 'review',
		string $itemIconClass = 'bi bi-check-circle-fill'
	) :array {
		return [
			'key'               => $key,
			'label'             => $label,
			'description'       => $description,
			'drill_bucket'      => $drillBucket,
			'item_icon_class'   => $itemIconClass,
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
	 *     action:string,
	 *     target?:string
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
		$groups = [
			'scans' => [
				'zone'     => 'scans',
				'total'    => 0,
				'severity' => 'good',
				'items'    => [],
			],
			'maintenance' => [
				'zone'     => 'maintenance',
				'total'    => 0,
				'severity' => 'good',
				'items'    => [],
			],
		];
		foreach ( $zoneGroups as $group ) {
			$groups[ $group[ 'slug' ] ] = [
				'zone'     => $group[ 'slug' ],
				'total'    => $group[ 'total_issues' ],
				'severity' => $group[ 'severity' ],
				'items'    => $group[ 'items' ],
			];
		}

		return [
			'generated_at'    => 1700000000,
			'summary'         => [
				'total'        => $totalItems,
				'severity'     => $severity,
				'is_all_clear' => !$hasItems,
			],
			'items'           => \array_merge( $groups[ 'scans' ][ 'items' ], $groups[ 'maintenance' ][ 'items' ] ),
			'groups'          => $groups,
			'summary_subtext' => $subtext,
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
	 *   action:string,
	 *   target?:string
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
	 *     action:string,
	 *     target?:string
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
	 *   action:string,
	 *   target:string
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

class PageActionsQueueActionRouter {

	private object $capture;

	public function __construct( object $capture ) {
		$this->capture = $capture;
	}

	public function action( string $action, array $actionData = [] ) :object {
		$this->capture->actionCalls[] = [
			'action'      => $action,
			'action_data' => $actionData,
		];

		return new class {
			public function payload() :array {
				return [];
			}
		};
	}
}

class PageActionsQueueLandingUnitTestDouble extends PageActionsQueueLanding {

	private array $assessmentRowsByZone;
	private array $attentionQuery;

	/**
	 * @param array{
	 *   scans:list<array{
	 *     key:string,
	 *     label:string,
	 *     description:string,
	 *     item_icon_class:string,
	 *     status:string,
	 *     status_label:string,
	 *     status_icon_class:string
	 *   }>,
	 *   maintenance:list<array{
	 *     key:string,
	 *     label:string,
	 *     description:string,
	 *     item_icon_class:string,
	 *     status:string,
	 *     status_label:string,
	 *     status_icon_class:string
	 *   }>
	 * } $assessmentRowsByZone
	 */
	public function __construct( array $assessmentRowsByZone, array $attentionQuery ) {
		$this->assessmentRowsByZone = $assessmentRowsByZone;
		$this->attentionQuery = $attentionQuery;
	}

	protected function buildAssessmentRowsByZone() :array {
		return $this->assessmentRowsByZone;
	}

	protected function buildAttentionQuery() :array {
		return $this->attentionQuery;
	}

	protected function renderBucketsLayer() :string {
		return '__BUCKETS_LAYER__';
	}

	protected function buildSummarySubtext() :string {
		return $this->attentionQuery[ 'summary_subtext' ];
	}
}
