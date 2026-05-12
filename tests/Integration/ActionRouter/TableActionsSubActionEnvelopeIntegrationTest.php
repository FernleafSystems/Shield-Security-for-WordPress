<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Investigation\InvestigationTableContract,
	Actions\InvestigationTableAction,
	Actions\SessionsTableAction,
	Actions\TrafficLogTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class TableActionsSubActionEnvelopeIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	public function testUnknownSubActionForTrafficReturnsFailureWithPageReload() :void {
		$payload = $this->processor()->processAction( TrafficLogTableAction::SLUG, [
			'sub_action' => 'unsupported_sub_action',
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
	}

	public function testUnknownSubActionForSessionsReturnsFailureWithoutPageReload() :void {
		$payload = $this->processor()->processAction( SessionsTableAction::SLUG, [
			'sub_action' => 'unsupported_sub_action',
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertArrayHasKey( 'message', $payload );
	}

	public function testUnknownSubActionForInvestigationKeepsStableErrorCode() :void {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION => 'unsupported_sub_action',
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertSame( 'unsupported_sub_action', $payload[ 'error_code' ] ?? '' );
	}
}
