<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	ActionResponse
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\WpDashboardSummary;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\{
	Handler,
	Meter\MeterSummary
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class WpDashboardSummaryIntegrationTest extends ShieldIntegrationTestCase {

	private int $adminUserId;

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );

		$this->adminUserId = $this->loginAsSecurityAdmin();

		self::con()->opts
			->optSet( 'enable_core_file_integrity_scan', 'Y' )
			->optSet( 'enable_wpvuln_scan', 'Y' )
			->optSet( 'enabled_scan_apc', 'Y' )
			->store();

		Transient::Delete( self::con()->prefix( 'dashboard-widget-v2-vars' ) );
		$this->resetBuiltMetersCache();
	}

	public function tear_down() {
		$this->resetBuiltMetersCache();
		Transient::Delete( self::con()->prefix( 'dashboard-widget-v2-vars' ) );
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

	private function setSummaryMeter( int $percentage, array $warning = [] ) :void {
		$ref = new \ReflectionClass( Handler::class );
		$prop = $ref->getProperty( 'BuiltMeters' );
		$prop->setAccessible( true );

		$rgbs = $percentage > 80
			? [ 16, 128, 0 ]
			: ( $percentage > 40 ? [ 200, 150, 10 ] : [ 200, 50, 10 ] );

		$meters = (array)$prop->getValue();
		$meters[ MeterSummary::SLUG ] = [
			'title'       => 'Summary',
			'subtitle'    => 'Summary',
			'warning'     => $warning,
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
		$prop->setValue( null, $meters );
	}

	private function resetBuiltMetersCache() :void {
		$ref = new \ReflectionClass( Handler::class );
		$prop = $ref->getProperty( 'BuiltMeters' );
		$prop->setAccessible( true );
		$prop->setValue( null, [] );
	}

	private function createCompletedScan( string $scanSlug, ?int $finishedAt = null ) :int {
		$dbh = self::con()->db_con->scans;
		$record = $dbh->getRecord();
		$record->scan = $scanSlug;
		$record->ready_at = \max( 1, ( $finishedAt ?? \time() ) - 60 );
		$record->finished_at = $finishedAt ?? \time();
		$dbh->getQueryInserter()->insert( $record );
		return (int)$dbh->getQuerySelector()->setOrderBy( 'id', 'DESC', true )->first()->id;
	}

	private function addScanResultMeta( int $scanId, string $metaKey ) :void {
		$resultItemsDb = self::con()->db_con->scan_result_items;
		$item = $resultItemsDb->getRecord();
		$item->item_type = 'f';
		$item->item_id = \uniqid( 'result-item-', true );
		$resultItemsDb->getQueryInserter()->insert( $item );
		$resultItemId = (int)$resultItemsDb->getQuerySelector()->setOrderBy( 'id', 'DESC', true )->first()->id;

		$scanResultsDb = self::con()->db_con->scan_results;
		$scanResult = $scanResultsDb->getRecord();
		$scanResult->scan_ref = $scanId;
		$scanResult->resultitem_ref = $resultItemId;
		$scanResultsDb->getQueryInserter()->insert( $scanResult );

		$metaDb = self::con()->db_con->scan_result_item_meta;
		$meta = $metaDb->getRecord();
		$meta->ri_ref = $resultItemId;
		$meta->meta_key = $metaKey;
		$meta->meta_value = 1;
		$metaDb->getQueryInserter()->insert( $meta );
	}

	public function test_render_returns_v2_template_and_marker() :void {
		$this->setSummaryMeter( 90 );
		$payload = $this->renderSummary()->payload();

		$this->assertSame( '/admin/admin_dashboard_widget_v2.twig', (string)( $payload[ 'render_template' ] ?? '' ) );
		$this->assertHtmlContainsMarker( 'shield-dashboard-widget-v2', (string)( $payload[ 'render_output' ] ?? '' ), 'Dashboard summary render' );
	}

	public function test_attention_rows_follow_severity_rank_order() :void {
		$this->setSummaryMeter( 90 );

		$afsId = $this->createCompletedScan( 'afs' );
		$this->addScanResultMeta( $afsId, 'is_mal' );
		$this->addScanResultMeta( $afsId, 'is_mal' );
		$this->addScanResultMeta( $afsId, 'is_in_core' );
		$this->addScanResultMeta( $afsId, 'is_in_plugin' );
		$this->addScanResultMeta( $afsId, 'is_in_plugin' );
		$this->addScanResultMeta( $afsId, 'is_in_plugin' );

		$wpvId = $this->createCompletedScan( 'wpv' );
		$this->addScanResultMeta( $wpvId, 'is_vulnerable' );

		$vars = $this->renderSummary()->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$keys = \array_column( $vars[ 'attention_items' ] ?? [], 'key' );

		$this->assertSame( [ 'malware', 'vulnerable_assets', 'wp_files' ], $keys );
	}

	public function test_cap_behavior_displays_three_rows_and_hidden_count() :void {
		$this->setSummaryMeter( 90 );

		$afsId = $this->createCompletedScan( 'afs' );
		$this->addScanResultMeta( $afsId, 'is_mal' );
		$this->addScanResultMeta( $afsId, 'is_in_core' );
		$this->addScanResultMeta( $afsId, 'is_in_plugin' );
		$this->addScanResultMeta( $afsId, 'is_in_theme' );

		$vars = $this->renderSummary()->payload()[ 'render_data' ][ 'vars' ] ?? [];

		$this->assertCount( 3, $vars[ 'attention_items' ] ?? [] );
		$this->assertSame( 4, (int)( $vars[ 'attention_total' ] ?? 0 ) );
		$this->assertSame( 1, (int)( $vars[ 'attention_hidden' ] ?? 0 ) );
	}

	public function test_all_clear_state_when_no_items_and_good_traffic() :void {
		$this->setSummaryMeter( 95 );
		$payload = $this->renderSummary()->payload();
		$vars = $payload[ 'render_data' ][ 'vars' ] ?? [];

		$this->assertTrue( (bool)( $vars[ 'is_all_clear' ] ?? false ) );
		$this->assertSame( 0, (int)( $vars[ 'attention_total' ] ?? -1 ) );
		$this->assertHtmlContainsMarker( 'attention-all-clear', (string)( $payload[ 'render_output' ] ?? '' ), 'All-clear dashboard state' );
	}

	public function test_non_good_without_concrete_items_injects_generic_score_row() :void {
		$this->setSummaryMeter( 55 );
		$vars = $this->renderSummary()->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$item = $vars[ 'attention_items' ][ 0 ] ?? [];

		$this->assertFalse( (bool)( $vars[ 'is_all_clear' ] ?? true ) );
		$this->assertSame( 'score_generic', (string)( $item[ 'key' ] ?? '' ) );
		$this->assertSame( 'warning', (string)( $item[ 'severity' ] ?? '' ) );
	}

	public function test_non_plugin_admin_hides_internal_links() :void {
		$this->setSummaryMeter( 95 );
		$subscriberId = self::factory()->user->create( [
			'role' => 'subscriber',
		] );
		\wp_set_current_user( $subscriberId );

		$payload = $this->renderSummary()->payload();
		$renderData = $payload[ 'render_data' ] ?? [];
		$html = (string)( $payload[ 'render_output' ] ?? '' );

		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'show_internal_links' ] ?? true ) );
		$this->assertHtmlNotContainsMarker( 'href="'.self::con()->plugin_urls->adminHome().'"', $html, 'Subscriber dashboard links' );
	}

	public function test_refresh_parameter_controls_cache_bypass() :void {
		$this->setSummaryMeter( 95 );
		$first = $this->renderSummary( true )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 95, (int)( $first[ 'security_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );

		$this->setSummaryMeter( 45 );
		$cached = $this->renderSummary( false )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 95, (int)( $cached[ 'security_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );

		$refreshed = $this->renderSummary( true )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 45, (int)( $refreshed[ 'security_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );
	}

	public function test_refresh_false_string_does_not_bypass_cache() :void {
		$this->setSummaryMeter( 95 );
		$first = $this->renderSummary( true )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 95, (int)( $first[ 'security_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );

		$this->setSummaryMeter( 45 );
		$cached = $this->renderSummary( 'false' )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 95, (int)( $cached[ 'security_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );

		$refreshed = $this->renderSummary( 'true' )->payload()[ 'render_data' ][ 'vars' ] ?? [];
		$this->assertSame( 45, (int)( $refreshed[ 'security_progress' ][ 'totals' ][ 'percentage' ] ?? 0 ) );
	}
}
