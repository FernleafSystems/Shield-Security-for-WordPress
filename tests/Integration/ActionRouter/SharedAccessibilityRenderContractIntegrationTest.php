<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\Components\OffCanvas\FormReportCreate,
	Actions\Render\Components\OffCanvas\IpAnalysis,
	Actions\Render\Components\Scans\ItemAnalysis\Container
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\HtmlDomAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class SharedAccessibilityRenderContractIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'activity_logs' );
		$this->requireDb( 'user_meta' );
		$this->loginAsSecurityAdmin();
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	public function test_report_create_offcanvas_content_renders_stable_title_id_contract() :void {
		$payload = $this->processor()->processAction( FormReportCreate::SLUG )->payload();
		$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'render_output' ] ?? '' ) );

		$this->assertXPathExists(
			$xpath,
			'//*[@id="AptoOffcanvasLabel" and contains(@class,"offcanvas-title") and normalize-space()!=""]',
			'Report create offcanvas title id contract'
		);
	}

	public function test_scan_item_analysis_container_renders_modal_title_and_valid_tab_relationships() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$scanResultId = TestDataFactory::insertScanResultItem( $scanId, [
			'path_fragment' => 'index.php',
			'is_in_core'    => 1,
		] );

		$payload = $this->processor()->processAction( Container::SLUG, [
			'rid' => $scanResultId,
		] )->payload();
		$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'render_output' ] ?? '' ) );

		$this->assertXPathExists(
			$xpath,
			'//*[contains(@class,"modal-title") and normalize-space()!=""]',
			'Scan item analysis modal title contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="tabInfo-tab" and @aria-controls="tabInfo" and @aria-selected="true"]',
			'Scan item analysis info tab contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="tabInfo" and @aria-labelledby="tabInfo-tab"]',
			'Scan item analysis info panel contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="tabHistory-tab" and @aria-controls="tabHistory" and @aria-selected="false"]',
			'Scan item analysis history tab contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="tabHistory" and @aria-labelledby="tabHistory-tab"]',
			'Scan item analysis history panel contract'
		);
	}

	public function test_ip_analysis_offcanvas_reuses_investigate_lookup_and_inline_tabs_contract() :void {
		$payload = $this->processor()->processAction( IpAnalysis::SLUG, [
			'ip' => '198.51.100.20',
		] )->payload();
		$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'render_output' ] ?? '' ) );

		$this->assertXPathExists(
			$xpath,
			'//*[@id="AptoOffcanvasLabel" and contains(@class,"offcanvas-title") and normalize-space()!=""]',
			'IP analysis offcanvas title contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//form[@data-investigate-panel-form="1"]//select[@data-investigate-select2="1"]',
			'IP analysis offcanvas lookup select contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " investigate-panel__tabs ")]//button[@data-bs-toggle="tab"]',
			'IP analysis offcanvas inline tabs contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(@class,"investigate-inline-ipanalyse")]//*[contains(@class,"shield-ipanalyse")]',
			'IP analysis offcanvas shared wrapper marker'
		);
	}
}
