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

	private object $controller;

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
		$this->controller = UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'comps'  => (object)[
					'site_query' => new class {
						public function scanRuntime() :array {
							return [ 'is_running' => false ];
						}
					},
					'sec_admin'  => new WpDashboardSummarySecAdminStub( false ),
				],
				'db_con' => (object)[],
				'cfg'    => (object)[
					'properties' => [
						'slug_parent'      => 'shield',
						'slug_plugin'      => 'security',
						'base_permissions' => 'manage_options',
					],
				],
				'this_req' => (object)[
					'is_security_admin' => false,
				],
				'user_can_base_permissions' => true,
			]
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_security_admin_disabled_renders_full_detail_for_wp_admin_request() :void {
		$this->setSecurityAdminState( false, false );

		$renderData = $this->invokeNonPublicMethod(
			new WpDashboardSummaryAttentionQueryTestDouble( $this->attentionQuery( [
				$this->attentionItem( 'malware', 'scans', 2, 'critical', 'Malware' ),
			] ) ),
			'getRenderData'
		);

		$this->assertFullDetailContract( $renderData, [ 'malware' ] );
	}

	public function test_security_admin_request_renders_full_detail_when_security_admin_enabled() :void {
		$this->setSecurityAdminState( true, true );

		$renderData = $this->invokeNonPublicMethod(
			new WpDashboardSummaryAttentionQueryTestDouble( $this->attentionQuery( [
				$this->attentionItem( 'malware', 'scans', 2, 'critical', 'Malware' ),
			] ) ),
			'getRenderData'
		);

		$this->assertFullDetailContract( $renderData, [ 'malware' ] );
	}

	public function test_security_admin_enabled_without_security_admin_request_renders_restricted_contract() :void {
		$this->setSecurityAdminState( true, false );

		$renderData = $this->invokeNonPublicMethod(
			new WpDashboardSummaryRestrictedNoCardBuilderTestDouble( $this->attentionQuery( [
				$this->attentionItem( 'malware', 'scans', 2, 'critical', 'Malware' ),
			] ) ),
			'getRenderData'
		);

		$this->assertRestrictedContract( $renderData, true, 'warning' );

		$renderData = $this->invokeNonPublicMethod(
			new WpDashboardSummaryRestrictedNoCardBuilderTestDouble( $this->attentionQuery( [] ) ),
			'getRenderData'
		);

		$this->assertRestrictedContract( $renderData, false, 'good' );
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

	private function setSecurityAdminState( bool $enabled, bool $isSecurityAdmin ) :void {
		$this->controller->comps->sec_admin->enabled = $enabled;
		$this->controller->this_req->is_security_admin = $isSecurityAdmin;
	}

	private function assertFullDetailContract( array $renderData, array $expectedRowKeys ) :void {
		$this->assertTrue( $renderData[ 'flags' ][ 'has_items' ] );
		$this->assertFalse( $renderData[ 'flags' ][ 'is_security_admin_restricted' ] );
		$this->assertTrue( $renderData[ 'flags' ][ 'show_issue_count' ] );
		$this->assertTrue( $renderData[ 'flags' ][ 'show_issue_details' ] );
		$this->assertSame( 'critical', $renderData[ 'vars' ][ 'shield_status' ] );
		$this->assertSame( 2, $renderData[ 'vars' ][ 'issue_count' ] );
		$this->assertSame( $expectedRowKeys, \array_column( $renderData[ 'vars' ][ 'rows' ], 'key' ) );
		$this->assertArrayNotHasKey( 'summary', $renderData[ 'vars' ] );
	}

	private function assertRestrictedContract( array $renderData, bool $hasItems, string $expectedStatus ) :void {
		$this->assertSame( $hasItems, $renderData[ 'flags' ][ 'has_items' ] );
		$this->assertTrue( $renderData[ 'flags' ][ 'is_security_admin_restricted' ] );
		$this->assertFalse( $renderData[ 'flags' ][ 'show_issue_count' ] );
		$this->assertFalse( $renderData[ 'flags' ][ 'show_issue_details' ] );
		$this->assertSame( '/admin/home', $renderData[ 'hrefs' ][ 'cta' ] );
		$this->assertSame( $expectedStatus, $renderData[ 'vars' ][ 'shield_status' ] );
		$this->assertSame( 0, $renderData[ 'vars' ][ 'issue_count' ] );
		$this->assertSame( [], $renderData[ 'vars' ][ 'rows' ] );
		$this->assertArrayNotHasKey( 'summary', $renderData[ 'vars' ] );
	}
}

class WpDashboardSummaryAttentionQueryTestDouble extends WpDashboardSummary {

	private array $attentionQuery;

	public function __construct( array $attentionQuery ) {
		$this->attentionQuery = $attentionQuery;
	}

	protected function buildAttentionQuery() :array {
		return $this->attentionQuery;
	}
}

class WpDashboardSummaryRestrictedNoCardBuilderTestDouble extends WpDashboardSummaryAttentionQueryTestDouble {

	protected function buildActionsQueueCardData( array $attentionQuery ) :array {
		unset( $attentionQuery );
		throw new \RuntimeException( 'Restricted widget must not build full actions queue card data.' );
	}
}

class WpDashboardSummarySecAdminStub {

	public bool $enabled;

	public function __construct( bool $enabled ) {
		$this->enabled = $enabled;
	}

	public function isEnabledSecAdmin() :bool {
		return $this->enabled;
	}
}
