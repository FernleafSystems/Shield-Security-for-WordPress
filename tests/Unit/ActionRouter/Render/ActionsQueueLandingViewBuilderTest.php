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
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueLandingViewBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	MaintenanceAssetFixtures,
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\{
	Request,
	Users
};

class ActionsQueueLandingViewBuilderTest extends BaseUnitTest {

	use MaintenanceAssetFixtures;

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
				$pairs = [];
				foreach ( $params as $key => $value ) {
					$pairs[] = $key.'='.$value;
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pairs );
			}
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installServices();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_maps_summary_tiles_and_all_clear_contract() :void {
		$payload = $this->buildQueuePayload(
			true,
			6,
			'critical',
			[
				$this->buildZoneGroup( 'scans', 'critical', 4, [
					$this->buildQueueItem( 'malware', 'scans', 'Malware', 3, 'critical' ),
					$this->buildQueueItem( 'wp_files', 'scans', 'WP Files', 1, 'warning' ),
				] ),
				$this->buildZoneGroup( 'maintenance', 'warning', 2, [
					$this->buildQueueItem( 'wp_updates', 'maintenance', 'WordPress Version', 2, 'warning' ),
				] ),
			]
		);

		$view = ( new ActionsQueueLandingViewBuilder() )->build( $payload, [
			'scans'       => [],
			'maintenance' => [],
		], 'Last scan: 3 minutes ago' );
		$zonesIndexed = $view[ 'zones_indexed' ] ?? [];
		$zoneTiles = $view[ 'zone_tiles' ] ?? [];
		$strip = $view[ 'severity_strip' ] ?? [];
		$allClear = $view[ 'all_clear' ] ?? [];

		$this->assertSame( true, $view[ 'summary' ][ 'has_items' ] ?? false );
		$this->assertSame( 6, $view[ 'summary' ][ 'total_items' ] ?? 0 );
		$this->assertSame( 'critical', $strip[ 'severity' ] ?? '' );
		$this->assertSame( 3, $strip[ 'critical_count' ] ?? 0 );
		$this->assertSame( 1, $strip[ 'warning_count' ] ?? 0 );
		$this->assertSame( 'Last scan: 3 minutes ago', $strip[ 'subtext' ] ?? '' );

		$this->assertSame( [ 'scans', 'maintenance' ], \array_keys( $zonesIndexed ) );
		$this->assertSame( [ 'scans', 'maintenance' ], \array_column( $zoneTiles, 'key' ) );
		$this->assertSame( 'Scans', $zonesIndexed[ 'scans' ][ 'label' ] ?? '' );
		$this->assertSame( 'Maintenance', $zonesIndexed[ 'maintenance' ][ 'label' ] ?? '' );

		$this->assertSame(
			[ 'scans', 'maintenance' ],
			\array_column( $allClear[ 'zone_chips' ] ?? [], 'slug' )
		);
	}

	public function test_assessment_rows_keep_clear_zones_interactive_without_creating_issue_counts() :void {
		$payload = $this->buildQueuePayload(
			false,
			0,
			'good',
			[
				$this->buildZoneGroup( 'scans', 'good', 0, [] ),
				$this->buildZoneGroup( 'maintenance', 'good', 0, [] ),
			]
		);

		$view = ( new ActionsQueueLandingViewBuilder() )->build( $payload, [
			'scans' => [
				[
					'key'               => 'wp_files',
					'label'             => 'WordPress Core Files',
					'description'       => 'All WordPress Core files appear to be clean and unmodified.',
					'status'            => 'good',
					'status_label'      => 'Good',
					'status_icon_class' => 'bi bi-check-circle-fill',
				],
			],
			'maintenance' => [
				[
					'key'               => 'wp_updates',
					'label'             => 'WordPress Version',
					'description'       => 'WordPress has all available upgrades applied.',
					'status'            => 'good',
					'status_label'      => 'Good',
					'status_icon_class' => 'bi bi-check-circle-fill',
				],
			],
		] );
		$zoneTiles = $view[ 'zone_tiles' ] ?? [];
		$zonesByKey = [];
		foreach ( $zoneTiles as $tile ) {
			$zonesByKey[ $tile[ 'key' ] ?? '' ] = $tile;
		}

		$this->assertTrue( (bool)( $zonesByKey[ 'scans' ][ 'is_enabled' ] ?? false ) );
		$this->assertFalse( (bool)( $zonesByKey[ 'scans' ][ 'has_issues' ] ?? true ) );
		$this->assertTrue( (bool)( $zonesByKey[ 'scans' ][ 'has_assessments' ] ?? false ) );
		$this->assertSame( 0, $zonesByKey[ 'scans' ][ 'total_issues' ] ?? null );
		$this->assertSame( [ 'wp_files' ], \array_column( $zonesByKey[ 'scans' ][ 'assessment_rows' ] ?? [], 'key' ) );

		$this->assertTrue( (bool)( $zonesByKey[ 'maintenance' ][ 'is_enabled' ] ?? false ) );
		$this->assertFalse( (bool)( $zonesByKey[ 'maintenance' ][ 'has_issues' ] ?? true ) );
		$this->assertTrue( (bool)( $zonesByKey[ 'maintenance' ][ 'has_assessments' ] ?? false ) );
		$this->assertSame( 'All clear', $zonesByKey[ 'maintenance' ][ 'summary_text' ] ?? '' );
	}

	public function test_build_normalizes_maintenance_items_once_and_builds_detail_groups_from_same_rows() :void {
		$this->installServices(
			[
				'plugins' => [
					'akismet/akismet.php' => [],
				],
				'updates' => [
					'akismet/akismet.php' => [ 'new_version' => '5.4.0' ],
				],
				'plugin_vos' => [
					'akismet/akismet.php' => $this->buildMaintenancePluginVo( 'akismet/akismet.php', 'Akismet Anti-Spam', '5.3.0' ),
				],
				'upgrade_urls' => [
					'akismet/akismet.php' => '/wp-admin/update.php?action=upgrade-plugin&plugin=akismet/akismet.php',
				],
			]
		);

		$view = ( new ActionsQueueLandingViewBuilder() )->build(
			$this->buildQueuePayload(
				true,
				1,
				'warning',
				[
					$this->buildZoneGroup( 'scans', 'good', 0, [] ),
					$this->buildZoneGroup( 'maintenance', 'warning', 1, [
						$this->buildQueueItem( 'wp_plugins_updates', 'maintenance', 'Plugins With Updates', 1, 'warning' ),
					] ),
				]
			),
			[
				'scans' => [],
				'maintenance' => [
					[
						'key'               => 'system_php_version',
						'label'             => 'PHP Version',
						'description'       => 'PHP is supported.',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
				],
			]
		);

		$zoneTiles = $view[ 'zone_tiles' ];
		$maintenance = \array_values( \array_filter(
			$zoneTiles,
			static fn( array $tile ) :bool => $tile[ 'key' ] === 'maintenance'
		) )[ 0 ];

		$this->assertSame( DetailExpansionType::SIMPLE_TABLE, $maintenance[ 'items' ][ 0 ][ 'expansion' ][ 'type' ] );
		$this->assertSame( 'wp_plugins_updates', $maintenance[ 'maintenance_detail_groups' ][ 0 ][ 'rows' ][ 0 ][ 'key' ] );
		$this->assertSame( [ 'warning', 'good' ], \array_column( $maintenance[ 'maintenance_detail_groups' ], 'status' ) );
	}

	public function test_build_filters_duplicate_good_assessment_rows_when_ignored_maintenance_item_is_rendered() :void {
		$view = ( new ActionsQueueLandingViewBuilder() )->build(
			$this->buildQueuePayload(
				false,
				0,
				'good',
				[
					$this->buildZoneGroup( 'scans', 'good', 0, [] ),
					$this->buildZoneGroup( 'maintenance', 'good', 0, [
						[
							'key'           => 'system_php_version',
							'zone'          => 'maintenance',
							'label'         => 'PHP Version',
							'count'         => 0,
							'severity'      => 'good',
							'description'   => 'This maintenance item is currently ignored.',
							'href'          => '/wp-admin/update-core.php',
							'action'        => 'Open',
							'target'        => '',
							'cta'           => [
								'label' => 'Open',
								'href'  => '/wp-admin/update-core.php',
							],
							'toggle_action' => [
								'label'       => 'Stop ignoring',
								'href'        => 'javascript:{}',
								'icon'        => 'bi bi-eye-fill',
								'tooltip'     => 'Stop ignoring this maintenance item',
								'ajax_action' => [ 'ex' => 'maintenance_item_unignore' ],
							],
							'expansion'     => [],
						],
					] ),
				]
			),
			[
				'scans'       => [],
				'maintenance' => [
					[
						'key'               => 'system_php_version',
						'label'             => 'PHP Version',
						'description'       => 'This maintenance item is currently ignored.',
						'status'            => 'good',
						'status_label'      => 'Good',
						'status_icon_class' => 'bi bi-check-circle-fill',
					],
				],
			]
		);

		$maintenance = \array_values( \array_filter(
			$view[ 'zone_tiles' ],
			static fn( array $tile ) :bool => $tile[ 'key' ] === 'maintenance'
		) )[ 0 ];

		$this->assertCount( 1, $maintenance[ 'items' ] );
		$this->assertCount( 0, $maintenance[ 'assessment_rows' ] );
		$this->assertCount( 1, $maintenance[ 'maintenance_detail_groups' ] );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
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
		PluginControllerInstaller::install( $controller );
	}

	private function installServices( array $pluginFixture = [], array $themeFixture = [] ) :void {
		ServicesState::installItems( \array_merge(
			$this->buildMaintenanceAssetServiceItems( $pluginFixture, $themeFixture ),
			[
				'service_request' => new class extends Request {
					public function ip() :string {
						return '127.0.0.1';
					}

					public function ts( bool $update = true ) :int {
						return 1700000000;
					}
				},
				'service_wpusers' => new class extends Users {
					public function getCurrentWpUserId() {
						return 0;
					}
				},
			]
		) );
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
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => $totalItems,
				'severity'     => $severity,
				'is_all_clear' => !$hasItems,
			],
			'items'        => \array_merge( $groups[ 'scans' ][ 'items' ], $groups[ 'maintenance' ][ 'items' ] ),
			'groups'       => $groups,
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
	 *   action:string,
	 *   target:string
	 * }
	 */
	private function buildQueueItem(
		string $key,
		string $zone,
		string $label,
		int $count,
		string $severity
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
			'target'      => '',
		];
	}
}
