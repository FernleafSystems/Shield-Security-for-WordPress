<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\WpDashboardSummary;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};

class WpDashboardSummaryBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		Functions\when( 'did_action' )->justReturn( 0 );
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'comps'  => (object)[],
				'db_con' => (object)[],
				'cfg'    => (object)[
					'properties' => [
						'slug_parent'      => 'shield',
						'slug_plugin'      => 'security',
						'base_permissions' => 'manage_options',
					],
				],
				'user_can_base_permissions' => true,
			]
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_render_data_uses_attention_contract_without_calling_scan_state_builder_path() :void {
		$renderData = $this->invokeNonPublicMethod(
			new WpDashboardSummaryNoScanStateTestDouble( $this->attentionQuery( [
				$this->attentionItem( 'malware', 'scans', 2, 'critical', 'Malware' ),
			] ) ),
			'getRenderData'
		);

		$this->assertTrue( $renderData[ 'flags' ][ 'has_items' ] );
		$this->assertSame( 'critical', $renderData[ 'vars' ][ 'shield_status' ] );
		$this->assertSame( [ 'malware' ], \array_column( $renderData[ 'vars' ][ 'rows' ], 'key' ) );
	}

	private function attentionQuery( array $scanItems, array $maintenanceItems = [] ) :array {
		$items = \array_values( \array_merge( $scanItems, $maintenanceItems ) );

		return [
			'generated_at' => 1700000000,
			'summary'      => [
				'total'        => (int)\array_sum( \array_column( $items, 'count' ) ),
				'severity'     => 'critical',
				'is_all_clear' => empty( $items ),
			],
			'items'        => $items,
			'groups'       => [
				'scans'       => [
					'zone'     => 'scans',
					'total'    => (int)\array_sum( \array_column( $scanItems, 'count' ) ),
					'severity' => empty( $scanItems ) ? 'good' : 'critical',
					'items'    => $scanItems,
				],
				'maintenance' => [
					'zone'     => 'maintenance',
					'total'    => (int)\array_sum( \array_column( $maintenanceItems, 'count' ) ),
					'severity' => empty( $maintenanceItems ) ? 'good' : 'warning',
					'items'    => $maintenanceItems,
				],
			],
		];
	}

	private function attentionItem( string $key, string $zone, int $count, string $severity, string $label ) :array {
		return [
			'key'                => $key,
			'zone'               => $zone,
			'source'             => $zone === 'scans' ? 'scan' : 'maintenance',
			'label'              => $label,
			'description'        => $label,
			'count'              => $count,
			'ignored_count'      => 0,
			'severity'           => $severity,
			'href'               => '/'.$key,
			'action'             => 'Open',
			'target'             => '',
			'supports_sub_items' => false,
		];
	}
}

class WpDashboardSummaryNoScanStateTestDouble extends WpDashboardSummary {

	private array $attentionQuery;

	public function __construct( array $attentionQuery ) {
		$this->attentionQuery = $attentionQuery;
	}

	protected function buildAttentionQuery() :array {
		return $this->attentionQuery;
	}

	protected function buildScanState() :array {
		throw new \RuntimeException( 'Dashboard summary must not build scan state directly.' );
	}
}
