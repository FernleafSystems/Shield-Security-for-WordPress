<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueLandingViewBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class ActionsQueueLandingViewBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		$this->installControllerStub();
	}

	protected function tearDown() :void {
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
	 *   action:string
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
		];
	}
}
