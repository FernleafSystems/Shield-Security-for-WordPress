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

	private function buildCardData( array $attentionQuery, array $scanRows = [] ) :array {
		return ( new ActionsQueueCardDataBuilder() )->build( $attentionQuery, $scanRows );
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

	private function scanRow( string $key, string $label, string $severity, int $count ) :array {
		return [
			'key'      => $key,
			'zone'     => 'scans',
			'label'    => $label,
			'text'     => $label,
			'count'    => $count,
			'severity' => $severity,
			'href'     => '/'.$key,
			'action'   => 'Open',
			'target'   => '',
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
			),
			[ $this->scanRow( 'malware', 'Malware', 'critical', 2 ) ]
		);

		$this->assertSame( 'critical', $data[ 'shield_status' ] );
		$this->assertSame( 'bi bi-shield-x', $data[ 'shield_icon_class' ] );
		$this->assertSame( 3, $data[ 'summary' ][ 'total_items' ] );
		$this->assertTrue( $data[ 'summary' ][ 'has_items' ] );
		$this->assertSame( 'actions', $data[ 'actions_lane' ][ 'mode' ] );
		$this->assertSame( 'status', $data[ 'actions_lane' ][ 'indicator_type' ] );
		$this->assertSame( 'critical', $data[ 'actions_lane' ][ 'indicator_severity' ] );
		$this->assertSame( ' has-critical', $data[ 'actions_lane' ][ 'extra_classes' ] );
		$this->assertSame( 'bi bi-shield-x', $data[ 'actions_lane' ][ 'icon_class' ] );
		$this->assertSame( '/admin/scans/overview', $data[ 'actions_lane' ][ 'href' ] );
	}

	public function test_build_marks_all_clear_when_ignored_scan_items_are_the_only_attention_items() :void {
		$data = $this->buildCardData(
			$this->attentionQuery( [
				$this->attentionItem( 'wp_files_ignored', 'scans', 2, 'warning', 'ignored-wp-label' ),
				$this->attentionItem( 'plugin_files_ignored', 'scans', 1, 'warning', 'ignored-plugin-label' ),
				$this->attentionItem( 'theme_files_ignored', 'scans', 3, 'warning', 'ignored-theme-label' ),
				$this->attentionItem( 'malware_ignored', 'scans', 4, 'warning', 'ignored-malware-label' ),
			] ),
			[
				$this->scanRow( 'wp_files_ignored', 'ignored-wp-label', 'warning', 2 ),
				$this->scanRow( 'plugin_files_ignored', 'ignored-plugin-label', 'warning', 1 ),
				$this->scanRow( 'theme_files_ignored', 'ignored-theme-label', 'warning', 3 ),
				$this->scanRow( 'malware_ignored', 'ignored-malware-label', 'warning', 4 ),
			]
		);

		$this->assertFalse( $data[ 'summary' ][ 'has_items' ] );
		$this->assertSame( 0, $data[ 'summary' ][ 'total_items' ] );
		$this->assertSame( 'good', $data[ 'shield_status' ] );
		$this->assertSame( [], $data[ 'actions_queue_rows' ] );
		$this->assertSame( 'good', $data[ 'actions_lane' ][ 'indicator_severity' ] );
	}

	public function test_build_rows_follow_scan_state_order_and_append_maintenance() :void {
		$scanItems = [
			$this->attentionItem( 'malware', 'scans', 4, 'critical', 'Malware' ),
			$this->attentionItem( 'vulnerable_assets', 'scans', 3, 'critical', 'Vulnerabilities' ),
			$this->attentionItem( 'wp_files', 'scans', 2, 'critical', 'WordPress Files' ),
			$this->attentionItem( 'plugin_files', 'scans', 5, 'warning', 'Plugin Files' ),
			$this->attentionItem( 'theme_files', 'scans', 1, 'warning', 'Theme Files' ),
			$this->attentionItem( 'abandoned', 'scans', 6, 'critical', 'Abandoned Assets' ),
			$this->attentionItem( 'file_locker', 'scans', 2, 'warning', 'File Locker' ),
		];
		$data = $this->buildCardData(
			$this->attentionQuery(
				$scanItems,
				[ $this->attentionItem( 'wp_updates', 'maintenance', 7, 'warning', 'WordPress Updates' ) ]
			),
			[
				$this->scanRow( 'malware', 'Malware', 'critical', 4 ),
				$this->scanRow( 'vulnerable_assets', 'Vulnerabilities', 'critical', 3 ),
				$this->scanRow( 'wp_files', 'WordPress Files', 'critical', 2 ),
				$this->scanRow( 'plugin_files', 'Plugin Files', 'warning', 5 ),
				$this->scanRow( 'plugin_files_ignored', 'Plugin Files', 'warning', 3 ),
				$this->scanRow( 'theme_files', 'Theme Files', 'warning', 1 ),
				$this->scanRow( 'abandoned', 'Abandoned Assets', 'critical', 6 ),
				$this->scanRow( 'file_locker', 'File Locker', 'warning', 2 ),
			]
		);

		$rows = $data[ 'actions_queue_rows' ];

		$this->assertSame(
			[ 'malware', 'vulnerable_assets', 'wp_files', 'plugin_files', 'theme_files', 'abandoned', 'file_locker', 'maintenance' ],
			\array_column( $rows, 'key' )
		);
		$this->assertSame( [ 4, 3, 2, 5, 1, 6, 2, 7 ], \array_column( $rows, 'count' ) );
		$this->assertSame(
			[
				'bi bi-bug',
				'bi bi-shield-exclamation',
				'bi bi-wordpress',
				'bi bi-plug',
				'bi bi-brush',
				'bi bi-archive',
				'bi bi-file-lock2',
				'bi bi-wrench',
			],
			\array_column( $rows, 'icon_class' )
		);
	}

	public function test_build_rows_only_include_visible_scan_items_and_warning_only_maintenance() :void {
		$data = $this->buildCardData(
			$this->attentionQuery(
				[],
				[ $this->attentionItem( 'wp_updates', 'maintenance', 2, 'warning', 'WordPress Updates' ) ]
			),
			[ $this->scanRow( 'plugin_files', 'Plugin Files', 'warning', 2 ) ]
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
}
