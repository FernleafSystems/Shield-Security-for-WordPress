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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};

class PageOperatorModeLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'comps'  => (object)[
					'site_query' => new class {
						public function scanRuntime() :array {
							return [ 'is_running' => false ];
						}
					},
				],
				'db_con' => (object)[],
			]
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_render_data_exposes_only_dashboard_strip_destinations_and_live_monitor() :void {
		$renderData = $this->invokeNonPublicMethod(
			new PageOperatorModeLandingTestDouble( $this->attentionQuery( [], [] ) ),
			'getRenderData'
		);

		$this->assertSame(
			[ 'dashboard_strip', 'destination_cards', 'live_monitor' ],
			\array_keys( $renderData[ 'vars' ] )
		);
		$this->assertSame(
			[ 'overall', 'summaries' ],
			\array_keys( $renderData[ 'vars' ][ 'dashboard_strip' ] )
		);
		$this->assertCount( 2, $renderData[ 'vars' ][ 'dashboard_strip' ][ 'summaries' ] );
		$this->assertCount( 3, $renderData[ 'vars' ][ 'destination_cards' ] );
		$this->assertArrayNotHasKey( 'strings', $renderData );
		$this->assertArrayNotHasKey( 'actions_queue_rows', $renderData[ 'vars' ] );
		$this->assertArrayNotHasKey( 'secondary_lanes', $renderData[ 'vars' ] );
	}

	public function test_destination_cards_have_strict_lightweight_contract_and_canonical_routes() :void {
		$cards = $this->invokeNonPublicMethod( new PageOperatorModeLanding(), 'buildDestinationCards' );

		$this->assertCount( 3, $cards );
		$this->assertSame(
			[ 'investigate', 'configure', 'reports' ],
			\array_column( $cards, 'mode' )
		);
		$this->assertSame(
			[ 'Investigate', 'Configure', 'Reports' ],
			\array_column( $cards, 'sidebar_label' )
		);
		$this->assertSame(
			[ 'Investigate Site', 'Configure', 'Reports' ],
			\array_column( $cards, 'title' )
		);
		$this->assertSame(
			[ 'Open Investigation', 'Open Configure', 'Open Reports' ],
			\array_column( $cards, 'cta' )
		);
		$this->assertSame(
			[ 'investigate', 'configure', 'reports' ],
			\array_column( $cards, 'accent' )
		);
		$this->assertSame(
			[ 'mode', 'sidebar_label', 'href', 'icon_class', 'accent', 'title', 'description', 'cta', 'accessible_label' ],
			\array_keys( $cards[ 0 ] )
		);
		foreach ( $cards as $card ) {
			$this->assertNotSame( '', $card[ 'href' ] );
			$this->assertNotSame( '', $card[ 'icon_class' ] );
			$this->assertNotSame( '', $card[ 'description' ] );
			$this->assertStringContainsString( $card[ 'title' ], $card[ 'accessible_label' ] );
			$this->assertStringContainsString( $card[ 'description' ], $card[ 'accessible_label' ] );
			$this->assertStringContainsString( $card[ 'cta' ], $card[ 'accessible_label' ] );
		}
	}

	public function test_dashboard_strip_is_passed_through_without_page_recalculation() :void {
		$renderData = $this->invokeNonPublicMethod(
			new PageOperatorModeLandingTestDouble( $this->attentionQuery(
				[ $this->attentionItem( 'malware', 'scans', 2, 'critical' ) ],
				[ $this->attentionItem( 'wp_updates', 'maintenance', 1, 'warning' ) ]
			) ),
			'getRenderData'
		);
		$strip = $renderData[ 'vars' ][ 'dashboard_strip' ];

		$this->assertSame( 'critical', $strip[ 'overall' ][ 'status' ] );
		$this->assertSame( 'Critical Action Required', $strip[ 'overall' ][ 'title' ] );
		$this->assertSame( [ 2, 1 ], \array_column( $strip[ 'summaries' ], 'count' ) );
		$this->assertSame( [ 'critical', 'warning' ], \array_column( $strip[ 'summaries' ], 'status' ) );
	}

	public function test_live_monitor_vars_use_current_compact_contract() :void {
		$vars = $this->invokeNonPublicMethod( new PageOperatorModeLanding(), 'buildLiveMonitorVars' );

		$this->assertArrayHasKey( 'is_collapsed', $vars );
		$this->assertIsBool( $vars[ 'is_collapsed' ] );
		$this->assertNotSame( '', $vars[ 'title' ] );
		$this->assertNotSame( '', $vars[ 'activity' ] );
		$this->assertNotSame( '', $vars[ 'traffic' ] );
		$this->assertNotSame( '', $vars[ 'loading' ] );
		$this->assertArrayNotHasKey( 'minimize', $vars );
		$this->assertArrayNotHasKey( 'expand', $vars );
	}

	private function attentionQuery( array $scanItems, array $maintenanceItems ) :array {
		$items = \array_values( \array_merge( $scanItems, $maintenanceItems ) );

		return [
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => (int)\array_sum( \array_column( $items, 'count' ) ),
				'severity'     => $this->highestSeverity( $items ),
				'is_all_clear' => empty( $items ),
			],
			'items'        => $items,
			'groups'       => [
				'scans'       => $this->attentionGroup( 'scans', $scanItems ),
				'maintenance' => $this->attentionGroup( 'maintenance', $maintenanceItems ),
			],
		];
	}

	private function attentionGroup( string $zone, array $items ) :array {
		return [
			'zone'     => $zone,
			'total'    => (int)\array_sum( \array_column( $items, 'count' ) ),
			'severity' => $this->highestSeverity( $items ),
			'items'    => $items,
		];
	}

	private function attentionItem( string $key, string $zone, int $count, string $severity ) :array {
		return [
			'key'                => $key,
			'zone'               => $zone,
			'source'             => $zone === 'scans' ? 'scan' : 'maintenance',
			'label'              => $key,
			'description'        => $key,
			'count'              => $count,
			'ignored_count'      => 0,
			'severity'           => $severity,
			'href'               => '/'.$key,
			'action'             => 'Open',
			'target'             => '',
			'supports_sub_items' => false,
		];
	}

	private function highestSeverity( array $items ) :string {
		$severities = \array_column( $items, 'severity' );
		if ( \in_array( 'critical', $severities, true ) ) {
			return 'critical';
		}
		if ( \in_array( 'warning', $severities, true ) ) {
			return 'warning';
		}

		return 'good';
	}
}

class PageOperatorModeLandingTestDouble extends PageOperatorModeLanding {

	private array $attentionQuery;

	public function __construct( array $attentionQuery ) {
		$this->attentionQuery = $attentionQuery;
	}

	protected function buildAttentionQuery() :array {
		return $this->attentionQuery;
	}
}
