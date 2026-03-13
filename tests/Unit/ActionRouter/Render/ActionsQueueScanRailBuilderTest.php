<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ActionsQueueScanRailMetrics;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AjaxBatchRequests;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueScanRailBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
};

class ActionsQueueScanRailBuilderTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias( static fn( string $single, string $plural, int $count ) :string => $count === 1 ? $single : $plural );
		Functions\when( 'sanitize_key' )->alias( static fn( string $value ) :string => \strtolower( \preg_replace( '/[^a-z0-9_]/', '', $value ) ?? '' ) );
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
		ServicesState::mergeItems( [
			'service_wpgeneral' => new class extends General {
				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}
			},
			'service_request'   => new class extends Request {
				public function ip() :string {
					return '127.0.0.1';
				}

				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
			'service_wpusers'   => new class extends Users {
				public function getCurrentWpUserId() {
					return 0;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_starts_non_summary_tabs_lazy_without_eager_counts() :void {
		$builder = new ActionsQueueScanRailBuilderTestDouble(
			true,
			true,
			true,
			true,
			true
		);

		$renderData = $builder->buildFromLandingData(
			$this->buildNeedsAttentionPayload(),
			[
				'scans'       => [
					[
						'key'               => 'plugin_files',
						'label'             => 'Plugin Files',
						'description'       => 'All clear',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
				],
				'maintenance' => [
					[
						'key'               => 'system_php_version',
						'label'             => 'PHP Version',
						'description'       => 'PHP version is supported.',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
				],
			]
		);

		$railTabs = $renderData[ 'vars' ][ 'rail_tabs' ] ?? [];
		$summaryTab = $this->findTabByKey( $railTabs, 'summary' );
		$wordpressTab = $this->findTabByKey( $railTabs, 'wordpress' );
		$maintenanceTab = $this->findTabByKey( $railTabs, 'maintenance' );
		$pluginsTab = $this->findTabByKey( $railTabs, 'plugins' );
		$vulnerabilitiesTab = $this->findTabByKey( $railTabs, 'vulnerabilities' );
		$malwareTab = $this->findTabByKey( $railTabs, 'malware' );

		$this->assertSame( 'critical', $renderData[ 'vars' ][ 'rail' ][ 'accent_status' ] ?? '' );
		$this->assertSame( 'Loading scan details...', $renderData[ 'strings' ][ 'pane_loading' ] ?? '' );
		$this->assertSame( 'No issues found in this section.', $renderData[ 'strings' ][ 'no_issues' ] ?? '' );
		$this->assertSame( ActionsQueueScanRailMetrics::SLUG, $renderData[ 'vars' ][ 'metrics_action' ][ 'ex' ] ?? '' );
		$this->assertSame( AjaxBatchRequests::SLUG, $renderData[ 'vars' ][ 'preload_action' ][ 'ex' ] ?? '' );
		$this->assertSame(
			[ 'summary', 'wordpress', 'plugins', 'themes', 'vulnerabilities', 'malware', 'file_locker', 'maintenance' ],
			\array_column( $railTabs, 'key' )
		);
		$this->assertTrue( (bool)( $summaryTab[ 'is_loaded' ] ?? false ) );
		$this->assertSame( 5, $summaryTab[ 'count' ] ?? -1 );
		$this->assertNotEmpty( $summaryTab[ 'items' ] ?? [] );
		$this->assertContains( 'wordpress', $this->extractRailSwitchTargets( $summaryTab[ 'items' ] ?? [] ) );
		$this->assertSame( 1, \count( \array_filter(
			$summaryTab[ 'items' ] ?? [],
			static fn( array $item ) :bool => (string)( $item[ 'attributes' ][ 'data-shield-rail-switch' ] ?? '' ) === 'maintenance'
		) ) );
		$this->assertContains( 'Plugin Files', $this->extractItemTitlesBySection( $summaryTab[ 'items' ] ?? [], 'All clear' ) );
		$this->assertNotContains( 'WordPress Version', \array_column( $summaryTab[ 'items' ] ?? [], 'title' ) );
		$this->assertNotContains( 'PHP Version', \array_column( $summaryTab[ 'items' ] ?? [], 'title' ) );
		$this->assertTrue( (bool)( $maintenanceTab[ 'is_loaded' ] ?? false ) );
		$this->assertSame( 2, $maintenanceTab[ 'count' ] ?? -1 );
		$this->assertSame( 'warning', $maintenanceTab[ 'status' ] ?? '' );
		$this->assertSame( 'Maintenance', $maintenanceTab[ 'label' ] ?? '' );
		$this->assertContains( 'WordPress Version', \array_column( $maintenanceTab[ 'items' ] ?? [], 'title' ) );
		$this->assertContains( 'PHP Version', \array_column( $maintenanceTab[ 'items' ] ?? [], 'title' ) );
		$this->assertSame( 'actions_queue', $wordpressTab[ 'render_action' ][ 'display_context' ] ?? '' );
		$this->assertSame( 'neutral', $pluginsTab[ 'status' ] ?? '' );
		$this->assertArrayHasKey( 'count', $pluginsTab );
		$this->assertNull( $pluginsTab[ 'count' ] );
		$this->assertTrue( (bool)( $pluginsTab[ 'show_count_placeholder' ] ?? false ) );
		$this->assertTrue( (bool)( $this->findTabByKey( $renderData[ 'vars' ][ 'rail' ][ 'items' ] ?? [], 'plugins' )[ 'show_count_placeholder' ] ?? false ) );
		$this->assertFalse( (bool)( $pluginsTab[ 'is_loaded' ] ?? true ) );
		$this->assertFalse( (bool)( $pluginsTab[ 'is_disabled' ] ?? true ) );
		$this->assertSame( '', $pluginsTab[ 'disabled_message' ] ?? 'unexpected' );
		$this->assertSame( 'neutral', $pluginsTab[ 'disabled_status' ] ?? '' );
		$this->assertSame( [], $pluginsTab[ 'items' ] ?? [ 'unexpected' ] );
		$this->assertSame( 'actions_queue', $pluginsTab[ 'render_action' ][ 'display_context' ] ?? '' );
		$this->assertSame( 'scanresults_malware', $malwareTab[ 'render_action' ][ 'render_slug' ] ?? '' );
		$this->assertArrayHasKey( 'count', $malwareTab );
		$this->assertNull( $malwareTab[ 'count' ] );
		$this->assertTrue( (bool)( $malwareTab[ 'show_count_placeholder' ] ?? false ) );
		$this->assertSame( 'scanresults_vulnerabilities', $vulnerabilitiesTab[ 'render_action' ][ 'render_slug' ] ?? '' );
		$this->assertSame( '', $renderData[ 'content' ][ 'section' ][ 'wordpress' ] ?? 'unexpected' );
	}

	public function test_build_vulnerabilities_pane_builds_items_only_on_demand() :void {
		$builder = new ActionsQueueScanRailBuilderTestDouble(
			false,
			false,
			false,
			true,
			false,
			[
				'count'    => 1,
				'status'   => 'critical',
				'sections' => [
					'abandoned' => [
						'label' => 'Abandoned Assets',
						'items' => [
							[
								'label'       => 'Old Theme',
								'description' => 'This asset appears to be abandoned and should be reviewed.',
								'severity'    => 'warning',
								'count'       => 1,
								'actions'     => [],
							],
						],
					],
				],
			]
		);

		$pane = $builder->buildVulnerabilitiesPane();

		$this->assertSame( 'warning', $pane[ 'status' ] ?? '' );
		$this->assertCount( 1, $pane[ 'items' ] ?? [] );
		$this->assertSame( 'Abandoned Assets', $pane[ 'items' ][ 0 ][ 'section_label' ] ?? '' );
	}

	public function test_build_keeps_disabled_review_tabs_visible_in_lazy_shell() :void {
		$builder = new ActionsQueueScanRailBuilderTestDouble(
			false,
			false,
			false,
			false,
			false
		);

		$renderData = $builder->buildFromLandingData( $this->buildNeedsAttentionPayload() );
		$railTabs = $renderData[ 'vars' ][ 'rail_tabs' ] ?? [];
		$tabsByKey = [];
		foreach ( $railTabs as $tab ) {
			$tabsByKey[ (string)( $tab[ 'key' ] ?? '' ) ] = $tab;
		}

		$this->assertSame(
			[ 'summary', 'plugins', 'themes', 'vulnerabilities', 'malware', 'file_locker', 'maintenance' ],
			\array_keys( $tabsByKey )
		);
		$this->assertTrue( (bool)( $tabsByKey[ 'maintenance' ][ 'is_loaded' ] ?? false ) );
		$this->assertSame( 2, $tabsByKey[ 'maintenance' ][ 'count' ] ?? -1 );
		$this->assertSame( 'warning', $tabsByKey[ 'maintenance' ][ 'status' ] ?? '' );
		$this->assertSame( 'scanresults_plugins', $tabsByKey[ 'plugins' ][ 'render_action' ][ 'render_slug' ] ?? '' );
		$this->assertSame( 'scanresults_themes', $tabsByKey[ 'themes' ][ 'render_action' ][ 'render_slug' ] ?? '' );
		$this->assertSame( 'scanresults_vulnerabilities', $tabsByKey[ 'vulnerabilities' ][ 'render_action' ][ 'render_slug' ] ?? '' );
		$this->assertSame( 'scanresults_malware', $tabsByKey[ 'malware' ][ 'render_action' ][ 'render_slug' ] ?? '' );
		$this->assertArrayNotHasKey( 'wordpress', $tabsByKey );
		$this->assertSame( 'actions_queue', $tabsByKey[ 'plugins' ][ 'render_action' ][ 'display_context' ] ?? '' );
		$this->assertSame( 'actions_queue', $tabsByKey[ 'themes' ][ 'render_action' ][ 'display_context' ] ?? '' );
		$this->assertSame( 'actions_queue', $tabsByKey[ 'file_locker' ][ 'render_action' ][ 'display_context' ] ?? '' );
		$this->assertTrue( (bool)( $tabsByKey[ 'plugins' ][ 'is_disabled' ] ?? false ) );
		$this->assertSame( 'plugins disabled', $tabsByKey[ 'plugins' ][ 'disabled_message' ] ?? '' );
		$this->assertSame( 'neutral', $tabsByKey[ 'plugins' ][ 'disabled_status' ] ?? '' );
		$this->assertSame( [], $tabsByKey[ 'plugins' ][ 'items' ] ?? [ 'unexpected' ] );
		$this->assertArrayHasKey( 'count', $tabsByKey[ 'plugins' ] );
		$this->assertArrayHasKey( 'count', $tabsByKey[ 'themes' ] );
		$this->assertArrayHasKey( 'count', $tabsByKey[ 'vulnerabilities' ] );
		$this->assertArrayHasKey( 'count', $tabsByKey[ 'malware' ] );
		$this->assertNull( $tabsByKey[ 'plugins' ][ 'count' ] );
		$this->assertNull( $tabsByKey[ 'themes' ][ 'count' ] );
		$this->assertNull( $tabsByKey[ 'vulnerabilities' ][ 'count' ] );
		$this->assertNull( $tabsByKey[ 'malware' ][ 'count' ] );
		$this->assertSame( 'neutral', $tabsByKey[ 'plugins' ][ 'status' ] ?? '' );
		$this->assertSame( 'neutral', $tabsByKey[ 'themes' ][ 'status' ] ?? '' );
		$this->assertSame( 'neutral', $tabsByKey[ 'vulnerabilities' ][ 'status' ] ?? '' );
		$this->assertSame( 'neutral', $tabsByKey[ 'malware' ][ 'status' ] ?? '' );
	}

	public function test_build_uses_canonical_initial_metrics_for_summary_and_rail_accent() :void {
		$builder = new ActionsQueueScanRailBuilderTestDouble(
			true,
			true,
			true,
			true,
			true,
			[
				'count'    => 0,
				'status'   => 'good',
				'sections' => [],
			],
			[
				'tabs' => [
					'summary' => [
						'count'  => 8,
						'status' => 'warning',
					],
					'maintenance' => [
						'count'  => 2,
						'status' => 'warning',
					],
				],
				'rail_accent_status' => 'warning',
			]
		);

		$renderData = $builder->buildFromLandingData( $this->buildNeedsAttentionPayload() );
		$summaryTab = $this->findTabByKey( $renderData[ 'vars' ][ 'rail_tabs' ] ?? [], 'summary' );

		$this->assertSame( 8, $summaryTab[ 'count' ] ?? -1 );
		$this->assertSame( 'warning', $summaryTab[ 'status' ] ?? '' );
		$this->assertSame( 'warning', $renderData[ 'vars' ][ 'rail' ][ 'accent_status' ] ?? '' );
	}

	public function test_build_supports_maintenance_only_queue_state() :void {
		$builder = new ActionsQueueScanRailBuilderTestDouble(
			false,
			false,
			false,
			false,
			false,
			[
				'count'    => 0,
				'status'   => 'good',
				'sections' => [],
			],
			[
				'tabs' => [
					'summary'     => [
						'count'  => 1,
						'status' => 'warning',
					],
					'maintenance' => [
						'count'  => 1,
						'status' => 'warning',
					],
				],
				'rail_accent_status' => 'warning',
			]
		);

		$renderData = $builder->buildFromLandingData( [
			'render_data' => [
				'vars' => [
					'zone_groups' => [
						[
							'slug'         => 'scans',
							'severity'     => 'good',
							'total_issues' => 0,
							'items'        => [],
						],
						[
							'slug'         => 'maintenance',
							'severity'     => 'warning',
							'total_issues' => 1,
							'items'        => [
								[
									'key'         => 'wp_updates',
									'zone'        => 'maintenance',
									'label'       => 'WordPress Version',
									'count'       => 1,
									'severity'    => 'warning',
									'description' => '1 update needs review.',
									'href'        => '/wp-updates',
									'action'      => 'Open',
									'target'      => '',
								],
							],
						],
					],
				],
			],
		], [
			'scans'       => [],
			'maintenance' => [
				[
					'key'               => 'system_php_version',
					'label'             => 'PHP Version',
					'description'       => 'PHP version is supported.',
					'status'            => 'good',
					'status_label'      => 'Good',
					'status_icon_class' => 'bi bi-check-circle-fill',
				],
			],
		] );

		$railTabs = $renderData[ 'vars' ][ 'rail_tabs' ] ?? [];
		$summaryTab = $this->findTabByKey( $railTabs, 'summary' );
		$maintenanceTab = $this->findTabByKey( $railTabs, 'maintenance' );

		$this->assertSame(
			[ 'summary', 'plugins', 'themes', 'vulnerabilities', 'malware', 'file_locker', 'maintenance' ],
			\array_column( $railTabs, 'key' )
		);
		$this->assertSame( 1, $summaryTab[ 'count' ] ?? -1 );
		$this->assertSame( 'warning', $summaryTab[ 'status' ] ?? '' );
		$this->assertSame( [ 'maintenance' ], $this->extractRailSwitchTargets( $summaryTab[ 'items' ] ?? [] ) );
		$this->assertNotContains( 'WordPress Version', \array_column( $summaryTab[ 'items' ] ?? [], 'title' ) );
		$this->assertNotContains( 'PHP Version', \array_column( $summaryTab[ 'items' ] ?? [], 'title' ) );
		$this->assertSame( 1, $maintenanceTab[ 'count' ] ?? -1 );
		$this->assertSame( 'warning', $maintenanceTab[ 'status' ] ?? '' );
		$this->assertSame( 'Open', $maintenanceTab[ 'items' ][ 0 ][ 'actions' ][ 0 ][ 'label' ] ?? '' );
		$this->assertSame( 'All clear', $maintenanceTab[ 'items' ][ 1 ][ 'section_label' ] ?? '' );
	}

	private function buildNeedsAttentionPayload() :array {
		return [
			'render_data' => [
				'vars' => [
					'zone_groups' => [
						[
							'slug'         => 'scans',
							'severity'     => 'critical',
							'total_issues' => 3,
							'items'        => [
								[
									'key'         => 'wp_files',
									'label'       => 'WordPress Files',
									'count'       => 2,
									'severity'    => 'critical',
									'description' => '2 files need review.',
									'href'        => '/wp-files',
									'action'      => 'Open',
								],
								[
									'key'         => 'vulnerable_assets',
									'label'       => 'Vulnerabilities',
									'count'       => 1,
									'severity'    => 'warning',
									'description' => '1 asset needs review.',
									'href'        => '/vulns',
									'action'      => 'Open',
								],
							],
						],
						[
							'slug'         => 'maintenance',
							'severity'     => 'warning',
							'total_issues' => 2,
							'items'        => [
								[
									'key'         => 'wp_updates',
									'zone'        => 'maintenance',
									'label'       => 'WordPress Version',
									'count'       => 2,
									'severity'    => 'warning',
									'description' => '2 updates need review.',
									'href'        => '/wp-updates',
									'action'      => 'Open',
									'target'      => '_blank',
								],
							],
						],
					],
				],
			],
		];
	}

	private function findTabByKey( array $tabs, string $key ) :array {
		foreach ( $tabs as $tab ) {
			if ( ( $tab[ 'key' ] ?? '' ) === $key ) {
				return $tab;
			}
		}
		$this->fail( 'Tab "'.$key.'" not found.' );
		return [];
	}

	private function extractRailSwitchTargets( array $items ) :array {
		return \array_values( \array_filter( \array_map(
			static fn( array $item ) :string => (string)( $item[ 'attributes' ][ 'data-shield-rail-switch' ] ?? '' ),
			$items
		) ) );
	}

	private function extractItemTitlesBySection( array $items, string $sectionLabel ) :array {
		return \array_values( \array_map(
			static fn( array $item ) :string => (string)( $item[ 'title' ] ?? '' ),
			\array_filter(
				$items,
				static fn( array $item ) :bool => (string)( $item[ 'section_label' ] ?? '' ) === $sectionLabel
			)
		) );
	}
}

class ActionsQueueScanRailBuilderTestDouble extends ActionsQueueScanRailBuilder {

	private bool $wordpressEnabled;
	private bool $pluginsEnabled;
	private bool $themesEnabled;
	private bool $vulnerabilitiesEnabled;
	private bool $malwareEnabled;
	private array $vulnerabilities;
	private array $initialMetrics;

	public function __construct(
		bool $wordpressEnabled,
		bool $pluginsEnabled,
		bool $themesEnabled,
		bool $vulnerabilitiesEnabled,
		bool $malwareEnabled,
		array $vulnerabilities = [
			'count'    => 0,
			'status'   => 'good',
			'sections' => [],
		],
		array $initialMetrics = [
			'tabs' => [
				'summary' => [
					'count'  => 5,
					'status' => 'critical',
				],
				'maintenance' => [
					'count'  => 2,
					'status' => 'warning',
				],
			],
			'rail_accent_status' => 'critical',
		]
	) {
		$this->wordpressEnabled = $wordpressEnabled;
		$this->pluginsEnabled = $pluginsEnabled;
		$this->themesEnabled = $themesEnabled;
		$this->vulnerabilitiesEnabled = $vulnerabilitiesEnabled;
		$this->malwareEnabled = $malwareEnabled;
		$this->vulnerabilities = $vulnerabilities;
		$this->initialMetrics = $initialMetrics;
	}

	protected function isWordpressTabEnabled() :bool {
		return $this->wordpressEnabled;
	}

	protected function isPluginsRailTabEnabled() :bool {
		return $this->pluginsEnabled;
	}

	protected function isThemesRailTabEnabled() :bool {
		return $this->themesEnabled;
	}

	protected function isVulnerabilitiesRailTabEnabled() :bool {
		return $this->vulnerabilitiesEnabled;
	}

	protected function isMalwareRailTabEnabled() :bool {
		return $this->malwareEnabled;
	}

	protected function getRailTabAvailability( string $tabKey ) :array {
		$isAvailable = match ( $tabKey ) {
			'wordpress' => $this->wordpressEnabled,
			'plugins' => $this->pluginsEnabled,
			'themes' => $this->themesEnabled,
			'vulnerabilities' => $this->vulnerabilitiesEnabled,
			'malware' => $this->malwareEnabled,
			'file_locker' => true,
			default => false,
		};

		return [
			'is_available' => $isAvailable,
			'show_in_actions_queue' => \in_array( $tabKey, [ 'plugins', 'themes', 'vulnerabilities', 'malware', 'file_locker' ], true )
				|| ( $tabKey === 'wordpress' && $this->wordpressEnabled ),
			'disabled_message' => $isAvailable ? '' : $tabKey.' disabled',
			'disabled_status' => 'neutral',
		];
	}

	protected function buildAjaxRenderActionData( string $actionClass, array $aux = [] ) :array {
		$map = [
			'Wordpress'       => 'scanresults_wordpress',
			'Plugins'         => 'scanresults_plugins',
			'Themes'          => 'scanresults_themes',
			'Vulnerabilities' => 'scanresults_vulnerabilities',
			'Malware'         => 'scanresults_malware',
			'FileLocker'      => 'scanresults_filelocker',
		];
		$parts = \explode( '\\', $actionClass );
		$actionName = \end( $parts ) ?: '';
		return [
			'render_slug' => $map[ $actionName ] ?? '',
		] + $aux;
	}

	protected function buildVulnerabilities() :array {
		return $this->vulnerabilities;
	}

	protected function buildInitialRailMetrics( array $needsAttentionPayload = [] ) :array {
		return $this->initialMetrics;
	}
}
