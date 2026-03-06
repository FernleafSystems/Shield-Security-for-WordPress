<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\Components\OffCanvas\FormReportCreate,
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
}
