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
			'//form[@data-investigate-panel-form="1" and @data-offcanvas-history-mode="replace"]//select[@data-investigate-select2="1"]',
			'IP analysis offcanvas lookup select contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " investigate-inline-ipanalyse ")]//*[@data-investigate-panel-tabs="1"]',
			'IP analysis offcanvas inline tabs host contract'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " investigate-inline-ipanalyse ")]//*[@data-investigate-panel-tabs="1"]//*[@data-investigate-panel-tab="1"]',
			0,
			'IP analysis offcanvas does not server-render duplicate inline tab buttons'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(@class,"investigate-inline-ipanalyse")]//*[contains(@class,"shield-ipanalyse")]',
			'IP analysis offcanvas shared wrapper marker'
		);

		$sourceTabs = $xpath->query(
			'//*[contains(@class,"investigate-inline-ipanalyse")]//*[contains(concat(" ", normalize-space(@class), " "), " shield-options-rail ")]//*[@data-bs-toggle="tab"]'
		);
		$this->assertNotFalse( $sourceTabs, 'IP analysis offcanvas source tab query failed' );
		$this->assertGreaterThan( 0, $sourceTabs->length, 'IP analysis offcanvas source tabs exist' );

		foreach ( $sourceTabs as $sourceTab ) {
			$this->assertInstanceOf( \DOMElement::class, $sourceTab );
			$target = \trim( $sourceTab->getAttribute( 'data-bs-target' ) );
			$controls = \trim( $sourceTab->getAttribute( 'aria-controls' ) );
			$tabId = \trim( $sourceTab->getAttribute( 'id' ) );

			$this->assertNotSame( '', $target, 'IP analysis offcanvas source tab target contract' );
			$this->assertStringStartsWith( '#', $target, 'IP analysis offcanvas source tab target prefix contract' );
			$this->assertNotSame( '', $controls, 'IP analysis offcanvas source tab controls contract' );
			$this->assertNotSame( '', $tabId, 'IP analysis offcanvas source tab id contract' );
			$this->assertSame( '#'.$controls, $target, 'IP analysis offcanvas source tab target/controls relationship' );

			$panel = $this->assertXPathExists(
				$xpath,
				'//*[@id="'.\htmlspecialchars( \ltrim( $target, '#' ), \ENT_QUOTES | \ENT_HTML5, 'UTF-8' ).'" and @aria-labelledby="'.\htmlspecialchars( $tabId, \ENT_QUOTES | \ENT_HTML5, 'UTF-8' ).'"]',
				'IP analysis offcanvas target panel relationship for '.$tabId
			);
			$this->assertInstanceOf( \DOMElement::class, $panel );
		}
	}
}
