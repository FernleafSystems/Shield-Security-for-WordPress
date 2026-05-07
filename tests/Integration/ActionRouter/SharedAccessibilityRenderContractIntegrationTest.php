<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\Components\OffCanvas\FormReportCreate,
	Actions\Render\Components\OffCanvas\IpAnalysis,
	Actions\Render\Components\Scans\ItemAnalysis\Container,
	Actions\Render\Components\UserMfa\ConfigPage
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

	public function test_user_mfa_config_renders_single_shared_shell_title_and_mfa_placeholder() :void {
		$payload = $this->processor()->processAction( ConfigPage::SLUG )->payload();
		$xpath = $this->createDomXPathFromHtml( (string)( $payload[ 'render_output' ] ?? '' ) );

		$this->assertXPathCount(
			$xpath,
			'//*[@id="ShieldAdminShellTitle" and self::h1 and normalize-space()!=""]',
			1,
			'MFA profile shared shell title contract'
		);
		$this->assertXPathCount(
			$xpath,
			'//h1[contains(translate(@style, "ABCDEFGHIJKLMNOPQRSTUVWXYZ ", "abcdefghijklmnopqrstuvwxyz"), "display:none")]',
			0,
			'MFA profile should not render an inline-hidden h1 override'
		);
		$this->assertXPathCount(
			$xpath,
			'//*[@id="ShieldMfaUserProfileForm" and contains(concat(" ", normalize-space(@class), " "), " shield_user_mfa_container ")]',
			1,
			'MFA profile placeholder container contract'
		);
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
			'//*[@id="tabInfo-tab" and @aria-controls="tabInfo"]',
			'Scan item analysis info tab contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="tabInfo" and @aria-labelledby="tabInfo-tab"]',
			'Scan item analysis info panel contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="tabContents-tab" and @aria-controls="tabContents"]',
			'Scan item analysis contents tab contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@id="tabContents" and @aria-labelledby="tabContents-tab"]',
			'Scan item analysis contents panel contract'
		);
	}

	public function test_ip_analysis_offcanvas_reuses_investigate_lookup_contract() :void {
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
		$this->assertIpAnalysisInlineHostAndSourceRailContract( $xpath );
	}

	private function assertIpAnalysisInlineHostAndSourceRailContract( \DOMXPath $xpath ) :void {
		$scopeQuery = '//*[contains(concat(" ", normalize-space(@class), " "), " investigate-inline-ipanalyse ")]';
		$this->assertXPathCount(
			$xpath,
			$scopeQuery.'//*[@data-investigate-panel-tabs="1"]',
			1,
			'IP analysis offcanvas inline tab host contract'
		);
		$this->assertXPathCount(
			$xpath,
			$scopeQuery.'//*[@data-investigate-panel-tabs="1"]//*[@data-investigate-panel-tab="1"]',
			0,
			'IP analysis offcanvas should not server-render proxy tabs'
		);
		$this->assertXPathCount(
			$xpath,
			$scopeQuery.'//*[@role="tabpanel" and string-length(@id) > 0 and string-length(@aria-labelledby) > 0]',
			4,
			'IP analysis offcanvas source panel contract'
		);

		$sourceTabs = $xpath->query(
			$scopeQuery.'//*[contains(concat(" ", normalize-space(@class), " "), " shield-options-rail ")]'
			.'//*[@data-bs-toggle="tab" and @role="tab" and string-length(@id) > 0'
			.' and string-length(@aria-controls) > 0 and starts-with(@data-bs-target, "#")]'
		);
		$this->assertNotFalse( $sourceTabs, 'IP analysis source rail tab query should be valid' );
		$this->assertSame( 4, $sourceTabs->length, 'IP analysis source rail tab count contract' );

		foreach ( $sourceTabs as $sourceTab ) {
			$this->assertInstanceOf( \DOMElement::class, $sourceTab );
			$tabId = $sourceTab->getAttribute( 'id' );
			$panelId = $sourceTab->getAttribute( 'aria-controls' );
			$this->assertSame(
				'#'.$panelId,
				$sourceTab->getAttribute( 'data-bs-target' ),
				'IP analysis source tab target should match controls'
			);
			$this->assertXPathExists(
				$xpath,
				\sprintf(
					'%s//*[@role="tabpanel" and @id="%s" and @aria-labelledby="%s"]',
					$scopeQuery,
					$panelId,
					$tabId
				),
				'IP analysis source tab/panel relationship contract'
			);
		}
	}
}
