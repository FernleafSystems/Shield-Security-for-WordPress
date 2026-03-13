<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\DetailExpansionType;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageActionsQueueLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	MaintenanceAssetFixtures,
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
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
		$this->capture->scansResultsRenderData = [
			'strings' => [
				'pane_loading'          => '__PANE_LOADING__',
				'no_issues'             => '__NO_ISSUES__',
				'results_tab_wordpress' => '__WORDPRESS_TAB__',
			],
			'vars'    => [
				'rail'            => [ 'status' => 'sentinel' ],
				'rail_tabs'       => [ [ 'key' => 'summary', 'count' => 5, 'status' => 'critical' ] ],
				'metrics_action'  => [ 'slug' => 'metrics-sentinel' ],
				'preload_action'  => [ 'slug' => 'preload-sentinel' ],
				'summary_rows'    => [ [ 'label' => 'Summary Row' ] ],
				'assessment_rows' => [ [ 'label' => 'Assessment Row' ] ],
			],
			'content' => [
				'section' => [
					'wordpress'       => '__WP_SECTION__',
					'plugins'         => '__PLUGINS_SECTION__',
					'themes'          => '__THEMES_SECTION__',
					'vulnerabilities' => '__VULNS_SECTION__',
					'malware'         => '__MALWARE_SECTION__',
					'filelocker'      => '__FILELOCKER_SECTION__',
				],
			],
		];

		$page = $this->newPage();
		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$strip = (array)( $vars[ 'severity_strip' ] ?? [] );
		$allClear = (array)( $vars[ 'all_clear' ] ?? [] );

		$this->assertSame( 'critical', $strip[ 'severity' ] ?? '' );
		$this->assertSame( 5, $strip[ 'total_items' ] ?? null );
		$this->assertSame( 2, $strip[ 'critical_count' ] ?? null );
		$this->assertSame( 1, $strip[ 'warning_count' ] ?? null );
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
		$this->assertSame( $this->capture->scansResultsRenderData, $vars[ 'scans_results' ] ?? [] );
		$this->assertSame( 1, $page->getScansResultsBuildCalls() );
	}

	public function test_scans_results_payload_is_built_when_queue_has_items_even_if_scans_zone_is_clear() :void {
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

		$this->assertNotEmpty( $vars[ 'scans_results' ] ?? [] );
		$this->assertSame( 1, $page->getScansResultsBuildCalls() );
	}

	public function test_landing_vars_keep_strip_total_aligned_with_queue_wide_rail_contract() :void {
		$this->capture->queuePayload = $this->buildQueuePayload(
			true,
			5,
			'critical',
			'',
			[
				$this->buildZoneGroup( 'scans', 'critical', 3, [
					$this->buildQueueItem( 'malware', 'scans', 'Malware', 2, 'critical' ),
					$this->buildQueueItem( 'vulnerable_assets', 'scans', 'Vulnerabilities', 1, 'warning' ),
				] ),
				$this->buildZoneGroup( 'maintenance', 'warning', 2, [
					$this->buildQueueItem( 'wp_updates', 'maintenance', 'WordPress Version', 2, 'warning' ),
				] ),
			]
		);

		$page = new PageActionsQueueLandingUnitTestDouble(
			$this->buildDefaultAssessmentRowsByZone(),
			[
				'strings' => [
					'pane_loading'          => 'Loading scan details...',
					'no_issues'             => 'No issues found in this section.',
					'results_tab_wordpress' => 'WordPress',
				],
				'vars'    => [
					'rail'            => [],
					'rail_tabs'       => [
						[ 'key' => 'summary', 'count' => 5, 'status' => 'critical' ],
						[ 'key' => 'maintenance', 'count' => 2, 'status' => 'warning' ],
					],
					'metrics_action'  => [ 'ex' => 'actions_queue_scan_rail_metrics' ],
					'preload_action'  => [],
					'summary_rows'    => [],
					'assessment_rows' => [],
				],
				'content' => [
					'section' => [
						'wordpress'       => '',
						'plugins'         => '',
						'themes'          => '',
						'vulnerabilities' => '',
						'malware'         => '',
						'filelocker'      => '',
					],
				],
			],
			$this->capture->queuePayload
		);

		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$strip = (array)( $vars[ 'severity_strip' ] ?? [] );
		$railTabs = (array)( $vars[ 'scans_results' ][ 'vars' ][ 'rail_tabs' ] ?? [] );

		$this->assertSame( 5, $strip[ 'total_items' ] ?? null );
		$this->assertSame( 5, $railTabs[ 0 ][ 'count' ] ?? null );
		$this->assertSame( 'maintenance', $railTabs[ 1 ][ 'key' ] ?? '' );
		$this->assertSame( 2, $railTabs[ 1 ][ 'count' ] ?? null );
	}

	public function test_scans_results_payload_is_skipped_when_queue_is_all_clear() :void {
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
		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );

		$this->assertSame( [], $vars[ 'scans_results' ][ 'vars' ][ 'metrics_action' ] ?? [ 'unexpected' ] );
		$this->assertSame( [], $vars[ 'scans_results' ][ 'vars' ][ 'rail_tabs' ] ?? [ 'unexpected' ] );
		$this->assertNotSame( '', $vars[ 'scans_results' ][ 'strings' ][ 'pane_loading' ] ?? '' );
		$this->assertNotSame( '', $vars[ 'scans_results' ][ 'strings' ][ 'no_issues' ] ?? '' );
		$this->assertSame( 0, $page->getScansResultsBuildCalls() );
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

	public function test_maintenance_asset_rows_get_eager_expansion_contracts_in_tile_items() :void {
		$this->installServices(
			[],
			[
				'updates' => [
					'akismet/akismet.php' => [ 'new_version' => '5.4.0' ],
				],
				'plugins' => [
					'akismet/akismet.php'     => [],
					'hello-dolly/hello.php'   => [],
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
			$vars[ 'zone_tiles' ] ?? [],
			static fn( array $tile ) :bool => ( $tile[ 'key' ] ?? '' ) === 'maintenance'
		) )[ 0 ] ?? [];
		$itemsByKey = [];
		foreach ( $maintenanceTile[ 'items' ] ?? [] as $item ) {
			$itemsByKey[ (string)( $item[ 'key' ] ?? '' ) ] = $item;
		}

		foreach ( [ 'wp_plugins_updates', 'wp_themes_updates', 'wp_plugins_inactive', 'wp_themes_inactive' ] as $key ) {
			$this->assertNotEmpty( $itemsByKey[ $key ][ 'expansion' ] ?? [], 'Expected expansion for '.$key );
			$this->assertSame( DetailExpansionType::SIMPLE_TABLE, $itemsByKey[ $key ][ 'expansion' ][ 'type' ] ?? '' );
		}
		$this->assertSame(
			'/wp-admin/plugins.php?s=hello-dolly%2Fhello.php',
			$itemsByKey[ 'wp_plugins_inactive' ][ 'expansion' ][ 'table' ][ 'rows' ][ 0 ][ 'action' ][ 'href' ] ?? ''
		);
		$this->assertSame( [], $itemsByKey[ 'wp_updates' ][ 'expansion' ] ?? [] );
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
				$this->buildAssessmentRow( 'wp_files', 'WordPress Files' ),
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
		$groups = $maintenance[ 'maintenance_detail_groups' ] ?? [];

		$this->assertSame( [ 'warning', 'good' ], \array_column( $groups, 'status' ) );
		$this->assertSame( [ 'system_ssl_certificate' ], \array_column( $groups[ 0 ][ 'rows' ] ?? [], 'key' ) );
		$this->assertSame( [ 'wp_updates', 'system_lib_openssl' ], \array_column( $groups[ 1 ][ 'rows' ] ?? [], 'key' ) );
	}

	public function test_all_clear_strings_stay_aligned_with_all_clear_view_contract() :void {
		$this->capture->queuePayload = $this->buildQueuePayload( false, 0, 'good', '', [] );

		$page = $this->newPage();
		$strings = $this->invokeNonPublicMethod( $page, 'getLandingStrings' );
		$allClear = $this->invokeNonPublicMethod( $page, 'getLandingVars' )[ 'all_clear' ] ?? [];

		$this->assertNotSame( '', $strings[ 'all_clear_title' ] ?? '' );
		$this->assertNotSame( '', $strings[ 'all_clear_subtitle' ] ?? '' );
		$this->assertSame( $strings[ 'all_clear_title' ] ?? '', $allClear[ 'title' ] ?? '' );
		$this->assertSame( $strings[ 'all_clear_subtitle' ] ?? '', $allClear[ 'subtitle' ] ?? '' );
		$this->assertSame( $strings[ 'all_clear_icon_class' ] ?? '', $allClear[ 'icon_class' ] ?? '' );
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

		$this->assertSame( 0, \count( $this->capture->actionCalls ) );
		$this->assertSame( 1, $page->getScansResultsBuildCalls() );
	}

	private function installControllerStub() :void {
		$this->capture = (object)[
			'actionCalls'           => [],
			'queuePayload'          => $this->buildQueuePayload( false, 0, 'good', '', [] ),
			'scansResultsRenderData' => $this->buildScansResultsContract(),
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function actionsQueueScans( string $zone = 'scans' ) :string {
				return '/admin/scans/overview?zone='.$zone;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->opts = new class {
			public function optGet( string $key ) :array {
				return $key === 'ignored_maintenance_items'
					? \array_fill_keys( [
						'wp_plugins_updates',
						'wp_themes_updates',
						'wp_plugins_inactive',
						'wp_themes_inactive',
						'wp_updates',
						'system_ssl_certificate',
						'system_php_version',
						'wp_db_password',
						'system_lib_openssl',
					], [] )
					: [];
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
			'license' => new class {
				public function hasValidWorkingLicense() :bool {
					return false;
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

				return new class {
					public function payload() :array {
						return [];
					}
				};
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	/**
	 * @param array{
	 *   scans:list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 *   }>,
	 *   maintenance:list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 *   }>
	 * }|null $assessmentRowsByZone
	 */
	private function newPage( ?array $assessmentRowsByZone = null, array $actionData = [] ) :PageActionsQueueLanding {
		$page = new PageActionsQueueLandingUnitTestDouble(
			$assessmentRowsByZone ?? $this->buildDefaultAssessmentRowsByZone(),
			$this->capture->scansResultsRenderData,
			$this->capture->queuePayload
		);
		$page->action_data = $actionData;
		return $page;
	}

	private function installServices( array $query = [], array $pluginFixture = [], array $themeFixture = [] ) :void {
		$assetServices = $this->buildMaintenanceAssetServiceItems( $pluginFixture, $themeFixture );
		unset( $assetServices[ 'service_wpgeneral' ] );

		ServicesState::installItems( \array_merge( [
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
				public function getAdminUrl( string $path = '', bool $wpmsOnly = false ) :string {
					return '/wp-admin/'.\ltrim( $path, '/' );
				}

				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}

				public function hasCoreUpdate() :bool {
					return false;
				}

				public function getOption( $sKey, $mDefault = false, $bIgnoreWPMS = false ) {
					return $mDefault;
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

				public function getHomeUrl( string $path = '', bool $wpms = false ) :string {
					return 'http://example.com/'.\ltrim( $path, '/' );
				}

				public function getWpUrl( string $path = '' ) :string {
					return 'http://example.com/'.\ltrim( $path, '/' );
				}
			},
			'service_wpusers' => new class extends Users {
				public function getCurrentWpUserId() {
					return 1;
				}
			},
		], $assetServices ) );
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
				$this->buildAssessmentRow( 'wp_files', 'WordPress Files' ),
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

	/**
	 * @return array{
	 *   strings:array{
	 *     pane_loading:string,
	 *     no_issues:string,
	 *     results_tab_wordpress:string
	 *   },
	 *   vars:array{
	 *     rail:array<string,mixed>,
	 *     rail_tabs:list<array<string,mixed>>,
	 *     metrics_action:array<string,mixed>,
	 *     preload_action:array<string,mixed>,
	 *     summary_rows:list<array<string,mixed>>,
	 *     assessment_rows:list<array<string,mixed>>
	 *   },
	 *   content:array{
	 *     section:array{
	 *       wordpress:string,
	 *       plugins:string,
	 *       themes:string,
	 *       vulnerabilities:string,
	 *       malware:string,
	 *       filelocker:string
	 *     }
	 *   }
	 * }
	 */
	private function buildScansResultsContract() :array {
		return [
			'strings' => [
				'pane_loading'         => 'Loading scan details...',
				'no_issues'            => 'No issues found in this section.',
				'results_tab_wordpress' => 'WordPress',
			],
			'vars'    => [
				'rail'            => [],
				'rail_tabs'       => [],
				'metrics_action'  => [],
				'preload_action'  => [],
				'summary_rows'    => [],
				'assessment_rows' => [],
			],
			'content' => [
				'section' => [
					'wordpress'       => '',
					'plugins'         => '',
					'themes'          => '',
					'vulnerabilities' => '',
					'malware'         => '',
					'filelocker'      => '',
				],
			],
		];
	}
}

class PageActionsQueueLandingUnitTestDouble extends PageActionsQueueLanding {

	private int $scansResultsBuildCalls = 0;
	private array $assessmentRowsByZone;
	private array $scansResultsRenderData;
	private array $attentionQuery;

	/**
	 * @param array{
	 *   scans:list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 *   }>,
	 *   maintenance:list<array{
	 *   key:string,
	 *   label:string,
	 *   description:string,
	 *   status:string,
	 *   status_label:string,
	 *   status_icon_class:string
	 *   }>
	 * } $assessmentRowsByZone
	 * @param array<string,mixed> $scansResultsRenderData
	 */
	public function __construct(
		array $assessmentRowsByZone,
		array $scansResultsRenderData,
		array $attentionQuery
	) {
		$this->assessmentRowsByZone = $assessmentRowsByZone;
		$this->scansResultsRenderData = $scansResultsRenderData;
		$this->attentionQuery = $attentionQuery;
	}

	protected function buildAssessmentRowsByZone() :array {
		return $this->assessmentRowsByZone;
	}

	protected function buildScansResultsRenderData() :array {
		++$this->scansResultsBuildCalls;
		return $this->scansResultsRenderData;
	}

	protected function buildAttentionQuery() :array {
		return $this->attentionQuery;
	}

	protected function buildSummarySubtext() :string {
		return $this->attentionQuery[ 'summary_subtext' ] ?? '';
	}

	public function getScansResultsBuildCalls() :int {
		return $this->scansResultsBuildCalls;
	}
}
