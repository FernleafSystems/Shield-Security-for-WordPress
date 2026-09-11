<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\ScanResultsLagWarning;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\ActionsQueueCardDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};

class ActionsQueueCardDataBuilderTest extends BaseUnitTest {

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

	private function buildCardData( array $attentionQuery ) :array {
		return ( new ActionsQueueCardDataBuilder() )->build( $attentionQuery );
	}

	private function attentionQuery(
		array $scanItems,
		array $maintenanceItems = []
	) :array {
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

	private function attentionItem( string $key, string $zone, int $count, string $severity, string $label = '' ) :array {
		return [
			'key'                => $key,
			'zone'               => $zone,
			'source'             => $zone === 'scans' ? 'scan' : 'maintenance',
			'label'              => $label === '' ? $key : $label,
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

	public function test_build_uses_summary_contract_for_actions_lane() :void {
		$data = $this->buildCardData(
			$this->attentionQuery(
				[ $this->attentionItem( 'malware', 'scans', 2, 'critical', 'Malware' ) ],
				[ $this->attentionItem( 'wp_updates', 'maintenance', 1, 'warning', 'WordPress Updates' ) ]
			)
		);

		$this->assertSame( 'critical', $data[ 'shield_status' ] );
		$this->assertSame( 3, $data[ 'summary' ][ 'total_items' ] );
		$this->assertTrue( $data[ 'summary' ][ 'has_items' ] );
		$this->assertSame( 'actions', $data[ 'actions_lane' ][ 'mode' ] );
		$this->assertSame( 'status', $data[ 'actions_lane' ][ 'indicator_type' ] );
		$this->assertSame( 'critical', $data[ 'actions_lane' ][ 'indicator_severity' ] );
		$this->assertIsString( $data[ 'shield_icon_class' ] );
		$this->assertIsString( $data[ 'actions_lane' ][ 'icon_class' ] );
		$this->assertIsString( $data[ 'actions_lane' ][ 'href' ] );
	}

	public function test_build_marks_all_clear_when_attention_query_is_empty() :void {
		$data = $this->buildCardData(
			$this->attentionQuery( [] )
		);

		$this->assertFalse( $data[ 'summary' ][ 'has_items' ] );
		$this->assertSame( 0, $data[ 'summary' ][ 'total_items' ] );
		$this->assertSame( 'good', $data[ 'shield_status' ] );
		$this->assertSame( [], $data[ 'actions_queue_rows' ] );
		$this->assertSame( 'good', $data[ 'actions_lane' ][ 'indicator_severity' ] );
		$this->assertSame(
			[ 'scans', 'maintenance', 'cloaked_plugin_detection' ],
			\array_column( $data[ 'all_clear' ][ 'checks' ], 'slug' )
		);
		$this->assertIsArray( $data[ 'all_clear' ][ 'zone_chips' ] );
		$this->assertGreaterThan( 0, \count( $data[ 'all_clear' ][ 'zone_chips' ] ) );
	}

	public function test_build_rows_follow_attention_scan_items_and_append_maintenance() :void {
		$scanItems = [
			$this->attentionItem( 'malware', 'scans', 4, 'critical', 'Malware' ),
			$this->attentionItem( 'vulnerable_assets', 'scans', 3, 'critical', 'Vulnerabilities' ),
			$this->attentionItem( 'wp_files', 'scans', 2, 'critical', 'WordPress Files' ),
			$this->attentionItem( 'plugin_files', 'scans', 5, 'warning', 'Plugin Files' ),
			$this->attentionItem( 'theme_files', 'scans', 1, 'warning', 'Theme Files' ),
			$this->attentionItem( 'abandoned', 'scans', 6, 'critical', 'Abandoned Assets' ),
			$this->attentionItem( 'file_locker', 'scans', 2, 'warning', 'File Locker' ),
			$this->attentionItem( 'hidden_plugins', 'scans', 2, 'critical', 'Cloaked Plugins' ),
		];
		$data = $this->buildCardData(
			$this->attentionQuery(
				$scanItems,
				[ $this->attentionItem( 'wp_updates', 'maintenance', 7, 'warning', 'WordPress Updates' ) ]
			)
		);

		$rows = $data[ 'actions_queue_rows' ];

		$this->assertSame(
			[ 'malware', 'vulnerable_assets', 'wp_files', 'plugin_files', 'theme_files', 'abandoned', 'file_locker', 'hidden_plugins', 'maintenance' ],
			\array_column( $rows, 'key' )
		);
		$this->assertSame( [ 4, 3, 2, 5, 1, 6, 2, 2, 7 ], \array_column( $rows, 'count' ) );
		$this->assertSame( 'Cloaked Plugins', $rows[ 7 ][ 'label' ] );
		$this->assertSame( 'critical', $rows[ 7 ][ 'severity' ] );
		$this->assertCount( \count( $rows ), \array_filter( \array_column( $rows, 'icon_class' ), '\is_string' ) );
	}

	public function test_build_rows_only_include_visible_scan_items_and_warning_only_maintenance() :void {
		$data = $this->buildCardData(
			$this->attentionQuery(
				[],
				[ $this->attentionItem( 'wp_updates', 'maintenance', 2, 'warning', 'WordPress Updates' ) ]
			)
		);

		$rows = $data[ 'actions_queue_rows' ];

		$this->assertSame( [ 'maintenance' ], \array_column( $rows, 'key' ) );
		$this->assertSame( 'warning', $data[ 'shield_status' ] );
		$this->assertSame( 'warning', $data[ 'actions_lane' ][ 'indicator_severity' ] );
	}

	public function test_build_uses_shared_runtime_warning_for_featured_subtitle() :void {
		PluginControllerInstaller::reset();
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'comps'  => (object)[
					'site_query' => new class {
						public function scanRuntime() :array {
							return [ 'is_running' => true ];
						}
					},
				],
				'db_con' => (object)[],
			]
		);

		$data = $this->buildCardData( $this->attentionQuery( [] ) );

		$this->assertSame( ( new ScanResultsLagWarning() )->getText(), $data[ 'subtitle' ] );
	}

	public function test_dashboard_strip_has_strict_group_owned_contract() :void {
		$attentionQuery = $this->attentionQuery(
			[ $this->attentionItem( 'plugin_files', 'scans', 2, 'warning', 'Plugin Files' ) ],
			[ $this->attentionItem( 'wp_updates', 'maintenance', 3, 'warning', 'WordPress Updates' ) ]
		);
		$attentionQuery[ 'summary' ] = [
			'total'        => 99,
			'severity'     => 'critical',
			'is_all_clear' => false,
		];

		$strip = $this->buildCardData( $attentionQuery )[ 'dashboard_strip' ];

		$this->assertSame(
			[ 'status', 'icon_class', 'title', 'summary', 'accessible_label' ],
			\array_keys( $strip[ 'overall' ] )
		);
		$this->assertSame( 'warning', $strip[ 'overall' ][ 'status' ] );
		$this->assertSame( 'Security Action Required', $strip[ 'overall' ][ 'title' ] );
		$this->assertSame( '5 issues need your attention.', $strip[ 'overall' ][ 'summary' ] );
		$this->assertSame(
			[ 'scans', 'maintenance' ],
			\array_column( $strip[ 'summaries' ], 'id' )
		);
		$this->assertSame(
			[ 'Security Issues', 'Maintenance' ],
			\array_column( $strip[ 'summaries' ], 'label' )
		);
		$this->assertSame( [ 2, 3 ], \array_column( $strip[ 'summaries' ], 'count' ) );
		$this->assertSame( [ 'warning', 'warning' ], \array_column( $strip[ 'summaries' ], 'status' ) );
		$this->assertSame(
			[ 'id', 'label', 'value', 'summary', 'accessible_label', 'count', 'status', 'href' ],
			\array_keys( $strip[ 'summaries' ][ 0 ] )
		);
		$this->assertSame( [ '2 security issues', '3 items due' ], \array_column( $strip[ 'summaries' ], 'value' ) );
		$this->assertSame(
			[ 'Security findings need review.', 'Routine maintenance items require review.' ],
			\array_column( $strip[ 'summaries' ], 'summary' )
		);
		$this->assertSame(
			$dataHrefs = \array_column( $strip[ 'summaries' ], 'href' ),
			\array_fill( 0, 2, $dataHrefs[ 0 ] )
		);
		$this->assertNotSame( '', $strip[ 'overall' ][ 'icon_class' ] );
		$this->assertNotSame( '', $strip[ 'overall' ][ 'accessible_label' ] );
		$this->assertNotSame( '', $strip[ 'summaries' ][ 0 ][ 'accessible_label' ] );
	}

	/**
	 * @dataProvider dashboardStripPrecedenceProvider
	 */
	public function test_dashboard_strip_overall_precedence(
		array $scanItems,
		array $maintenanceItems,
		string $expectedStatus,
		string $expectedTitle
	) :void {
		$overall = $this->buildCardData(
			$this->attentionQuery( $scanItems, $maintenanceItems )
		)[ 'dashboard_strip' ][ 'overall' ];

		$this->assertSame( $expectedStatus, $overall[ 'status' ] );
		$this->assertSame( $expectedTitle, $overall[ 'title' ] );
	}

	public function dashboardStripPrecedenceProvider() :array {
		return [
			'all clear'                    => [ [], [], 'good', 'All Clear' ],
			'critical maintenance'         => [
				[ $this->attentionItem( 'plugin_files', 'scans', 1, 'warning' ) ],
				[ $this->attentionItem( 'wp_updates', 'maintenance', 1, 'critical' ) ],
				'critical',
				'Critical Action Required',
			],
			'warning security before upkeep' => [
				[ $this->attentionItem( 'plugin_files', 'scans', 1, 'warning' ) ],
				[ $this->attentionItem( 'wp_updates', 'maintenance', 1, 'warning' ) ],
				'warning',
				'Security Action Required',
			],
			'maintenance only'             => [
				[],
				[ $this->attentionItem( 'wp_updates', 'maintenance', 1, 'warning' ) ],
				'warning',
				'Maintenance Action Required',
			],
		];
	}
}
