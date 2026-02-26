<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	ActionResponse
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\WpDashboardSummary;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Component\Base as MeterComponent,
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\BuiltMetersFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class WpDashboardSummaryIntegrationTest extends ShieldIntegrationTestCase {

	use BuiltMetersFixture;

	private const WIDGET_CACHE_KEY = 'dashboard-widget-v3-vars';

	private int $adminUserId;

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->adminUserId = $this->loginAsSecurityAdmin();
		$this->enablePremiumCapabilities( [
			'scan_malware_local',
			'scan_vulnerabilities',
		] );

		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'enable_wpvuln_scan', 'Y' )
			->optSet( 'enabled_scan_apc', 'Y' )
			->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
			->store();

		Transient::Delete( self::con()->prefix( self::WIDGET_CACHE_KEY ) );
		$this->resetBuiltMetersCache();
		$this->setOverallConfigMeterComponents( [] );
	}

	public function tear_down() {
		$this->resetBuiltMetersCache();
		Transient::Delete( self::con()->prefix( self::WIDGET_CACHE_KEY ) );
		parent::tear_down();
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function renderSummary( $refresh = true ) :ActionResponse {
		return $this->processor()->processAction( WpDashboardSummary::SLUG, [
			'refresh' => $refresh,
		] );
	}

	private function setSummaryMeters( int $combinedPercentage, int $configPercentage ) :void {
		$ref = new \ReflectionClass( Handler::class );
		$combinedProp = $ref->getProperty( 'BuiltMeters' );
		$combinedProp->setAccessible( true );
		$channelProp = $ref->getProperty( 'BuiltMetersByChannel' );
		$channelProp->setAccessible( true );

		$meters = (array)$combinedProp->getValue();
		$meters[ MeterSummary::SLUG ] = $this->meterFixture( $combinedPercentage );
		$combinedProp->setValue( null, $meters );

		$metersByChannel = (array)$channelProp->getValue();
		$metersByChannel[ MeterSummary::SLUG ] = \array_merge(
			$metersByChannel[ MeterSummary::SLUG ] ?? [],
			[
				MeterComponent::CHANNEL_CONFIG => $this->meterFixture( $configPercentage ),
			]
		);
		$channelProp->setValue( null, $metersByChannel );
	}

	private function meterFixture( int $percentage ) :array {
		$rgbs = $percentage > 80
			? [ 16, 128, 0 ]
			: ( $percentage > 40 ? [ 200, 150, 10 ] : [ 200, 50, 10 ] );
		return [
			'title'       => 'Summary',
			'subtitle'    => 'Summary',
			'warning'     => [],
			'description' => [],
			'components'  => [],
			'totals'      => [
				'score'        => 0,
				'max_weight'   => 0,
				'percentage'   => $percentage,
				'letter_score' => 'A',
			],
			'status'      => 'h',
			'rgbs'        => $rgbs,
			'has_critical'=> false,
		];
	}

	public function test_render_returns_v2_template_and_marker() :void {
		$this->setSummaryMeters( 90, 90 );
		$payload = $this->renderSummary()->payload();

		$this->assertSame( '/admin/admin_dashboard_widget_v2.twig', (string)( $payload[ 'render_template' ] ?? '' ) );
		$this->assertHtmlContainsMarker( 'shield-dashboard-widget-v2', (string)( $payload[ 'render_output' ] ?? '' ), 'Dashboard summary render' );
	}

	public function test_config_score_uses_config_channel_meter() :void {
		$this->setSummaryMeters( 33, 92 );
		$vars = $this->renderSummary()->payload()[ 'render_data' ][ 'vars' ] ?? [];

		$this->assertSame( 92, (int)( $vars[ 'config_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );
		$this->assertSame( 'good', (string)( $vars[ 'config_traffic' ] ?? '' ) );
	}

	public function test_action_total_and_severity_reflect_scan_findings() :void {
		$this->setSummaryMeters( 90, 90 );
		$this->assertTrue( self::con()->comps->scans->AFS()->isEnabledMalwareScanPHP() );
		$this->assertTrue( self::con()->comps->scans->WPV()->isEnabled() );

		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_mal' );
		TestDataFactory::insertScanResultMeta( $afsId, 'is_mal' );

		$wpvId = TestDataFactory::insertCompletedScan( 'wpv' );
		TestDataFactory::insertScanResultMeta( $wpvId, 'is_vulnerable' );

		$vars = $this->renderSummary()->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertGreaterThanOrEqual( 2, (int)( $vars[ 'action_total' ] ?? 0 ) );
		$this->assertSame( 'critical', (string)( $vars[ 'action_traffic' ] ?? '' ) );
	}

	public function test_all_clear_state_when_no_items_and_good_traffic() :void {
		$this->setSummaryMeters( 95, 95 );
		$payload = $this->renderSummary()->payload();
		$vars = $payload[ 'render_data' ][ 'vars' ] ?? [];

		$this->assertTrue( (bool)( $vars[ 'is_all_clear' ] ?? false ) );
		$this->assertSame( 0, (int)( $vars[ 'action_total' ] ?? -1 ) );
		$this->assertHtmlContainsMarker( 'attention-all-clear', (string)( $payload[ 'render_output' ] ?? '' ), 'All-clear dashboard state' );
	}

	public function test_unprotected_maintenance_component_is_included_in_attention_rows() :void {
		$this->setSummaryMeters( 95, 95 );
		$this->setOverallConfigMeterComponents( [
			[
				'slug'              => 'wp_updates',
				'is_protected'      => false,
				'title'             => 'WordPress Version',
				'title_unprotected' => 'WordPress Version',
				'desc_unprotected'  => 'There is an upgrade available for WordPress.',
				'href_full'         => self::con()->plugin_urls->adminHome(),
				'fix'               => 'Fix',
			],
		] );

		$vars = $this->renderSummary()->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertGreaterThanOrEqual( 1, (int)( $vars[ 'action_total' ] ?? 0 ) );
		$this->assertSame( 'warning', (string)( $vars[ 'action_traffic' ] ?? '' ) );
		$this->assertFalse( (bool)( $vars[ 'is_all_clear' ] ?? true ) );
	}

	public function test_non_plugin_admin_hides_internal_links() :void {
		$this->setSummaryMeters( 95, 95 );
		$subscriberId = self::factory()->user->create( [
			'role' => 'subscriber',
		] );
		\wp_set_current_user( $subscriberId );

		$payload = $this->renderSummary()->payload();
		$renderData = $payload[ 'render_data' ] ?? [];
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'show_internal_links' ] ?? true ) );
		$this->assertHtmlNotContainsMarker( 'href="'.self::con()->plugin_urls->adminHome().'"', $html, 'Subscriber dashboard links' );
		$this->assertHtmlNotContainsMarker(
			'href="'.self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_SCANS, PluginNavs::SUBNAV_SCANS_RESULTS ).'"',
			$html,
			'Subscriber scans links'
		);
	}

	public function test_refresh_parameter_controls_cache_bypass() :void {
		$this->setSummaryMeters( 95, 95 );
		$first = $this->renderSummary( true )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 95, (int)( $first[ 'config_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );

		$this->setSummaryMeters( 45, 45 );
		$cached = $this->renderSummary( false )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 95, (int)( $cached[ 'config_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );

		$refreshed = $this->renderSummary( true )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 45, (int)( $refreshed[ 'config_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );
	}

	public function test_refresh_false_string_does_not_bypass_cache() :void {
		$this->setSummaryMeters( 95, 95 );
		$first = $this->renderSummary( true )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 95, (int)( $first[ 'config_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );

		$this->setSummaryMeters( 45, 45 );
		$cached = $this->renderSummary( 'false' )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 95, (int)( $cached[ 'config_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );

		$refreshed = $this->renderSummary( 'true' )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 45, (int)( $refreshed[ 'config_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );
	}
}
