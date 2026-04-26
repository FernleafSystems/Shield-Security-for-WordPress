<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Render\Components\Scans\ScansProgress,
	Actions\ScansBase
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\HtmlDomAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ScansProgressModalRenderIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
	}

	/**
	 * @dataProvider modalStateProvider
	 */
	public function test_scan_progress_renders_shared_modal_content_contract( string $modalState, string $expectedBusy ) :void {
		$payload = $this->processor()->processAction( ScansProgress::SLUG, [
			'modal_state'     => $modalState,
			'current_scan'    => 'scan-contract-current',
			'remaining_scans' => 'scan-contract-remaining',
			'progress'        => 42,
		] )->payload();
		$html = (string)( $payload[ 'render_output' ] ?? '' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertNotSame( '', $html );
		$this->assertHtmlNotContainsMarker( 'Exception during render', $html, 'Scan progress modal render' );
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " modal-header ")]//*[contains(concat(" ", normalize-space(@class), " "), " modal-title ") and normalize-space()!=""]',
			'Scan progress shared modal header/title contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " modal-body ")]//*[@data-shield-scan-modal-state="'.$modalState.'" and @aria-busy="'.$expectedBusy.'"]',
			'Scan progress modal state contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " modal-footer ")]',
			'Scan progress shared modal footer contract'
		);
	}

	public function modalStateProvider() :array {
		return [
			'initiating' => [ ScansBase::SCAN_MODAL_STATE_INITIATING, 'true' ],
			'running'    => [ ScansBase::SCAN_MODAL_STATE_RUNNING, 'true' ],
			'completed'  => [ ScansBase::SCAN_MODAL_STATE_COMPLETED, 'false' ],
			'failed'     => [ ScansBase::SCAN_MODAL_STATE_FAILED, 'false' ],
		];
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}
}
