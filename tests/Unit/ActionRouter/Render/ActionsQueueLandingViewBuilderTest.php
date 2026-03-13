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

class ActionsQueueLandingViewBuilderTest extends BaseUnitTest {

	use MaintenanceAssetFixtures;

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
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( $this->buildMaintenanceAssetServiceItems() );
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
			'Last scan: 3 minutes ago',
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

		$view = ( new ActionsQueueLandingViewBuilder() )->build( $payload );
		$zonesIndexed = $view[ 'zones_indexed' ] ?? [];
		$zoneTiles = $view[ 'zone_tiles' ] ?? [];
		$strip = $view[ 'severity_strip' ] ?? [];
		$allClear = $view[ 'all_clear' ] ?? [];

		$this->assertSame( true, $view[ 'summary' ][ 'has_items' ] ?? false );
		$this->assertSame( 6, $view[ 'summary' ][ 'total_items' ] ?? 0 );
		$this->assertSame( 'critical', $strip[ 'severity' ] ?? '' );
		$this->assertSame( 3, $strip[ 'critical_count' ] ?? 0 );
		$this->assertSame( 3, $strip[ 'warning_count' ] ?? 0 );
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
			'',
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
		ServicesState::installItems( $this->buildMaintenanceAssetServiceItems(
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
		) );

		$view = ( new ActionsQueueLandingViewBuilder() )->build(
			$this->buildQueuePayload(
				true,
				1,
				'warning',
				'',
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

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		PluginControllerInstaller::install( $controller );
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
