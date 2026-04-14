<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\Render\Components\Scans\Results\Wordpress,
	CaptureAjaxAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class CaptureAjaxActionTransportConsistencyIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
	}

	public function tear_down() {
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_post_transport_executes_ajax_render_request() :void {
		$request = ActionData::BuildAjaxRender( Wordpress::class );
		$this->applyCurrentShieldAjaxRequest( $request, false );

		$subject = new CaptureAjaxActionTransportConsistencyTestDouble();
		$issuePayload = $subject->buildIssuePayloadForTest();

		$this->assertTrue( $subject->canRunForTest() );
		$this->assertTrue( (bool)( $issuePayload[ 'success' ] ?? false ) );
		$this->assertSame( 200, $issuePayload[ 'status_code' ] ?? null );
		$this->assertTrue( (bool)( $issuePayload[ 'data' ][ 'success' ] ?? false ) );
		$this->assertNotSame( '', \trim( (string)( $issuePayload[ 'data' ][ 'html' ] ?? '' ) ) );
		$this->assertArrayNotHasKey( 'action_data', $issuePayload[ 'data' ] ?? [] );
	}

	public function test_query_only_transport_does_not_make_ajax_capture_runnable() :void {
		$request = ActionData::BuildAjaxRender( Wordpress::class );
		$this->applyCurrentShieldAjaxRequestWithQuery( $request, [], false );

		$this->assertFalse( ( new CaptureAjaxActionTransportConsistencyTestDouble() )->canRunForTest() );
	}

	public function test_query_only_nonce_does_not_authorize_post_ajax_request() :void {
		$request = ActionData::BuildAjaxRender( Wordpress::class );
		$query = [
			ActionData::FIELD_NONCE => (string)( $request[ ActionData::FIELD_NONCE ] ?? '' ),
		];
		unset( $request[ ActionData::FIELD_NONCE ] );
		$this->applyCurrentShieldAjaxRequestWithQuery( $query, $request, false );

		$subject = new CaptureAjaxActionTransportConsistencyTestDouble();
		$issuePayload = $subject->buildIssuePayloadForTest();

		$this->assertTrue( $subject->canRunForTest() );
		$this->assertFalse( (bool)( $issuePayload[ 'success' ] ?? true ) );
		$this->assertSame( 401, $issuePayload[ 'status_code' ] ?? null );
		$this->assertFalse( (bool)( $issuePayload[ 'data' ][ 'success' ] ?? true ) );
	}

	public function test_query_only_action_slug_does_not_steer_post_ajax_payload() :void {
		$request = ActionData::BuildAjaxRender( Wordpress::class );
		$query = [
			ActionData::FIELD_ACTION  => (string)( $request[ ActionData::FIELD_ACTION ] ?? '' ),
			ActionData::FIELD_EXECUTE => (string)( $request[ ActionData::FIELD_EXECUTE ] ?? '' ),
			ActionData::FIELD_NONCE   => (string)( $request[ ActionData::FIELD_NONCE ] ?? '' ),
		];
		unset(
			$request[ ActionData::FIELD_ACTION ],
			$request[ ActionData::FIELD_EXECUTE ],
			$request[ ActionData::FIELD_NONCE ]
		);
		$this->applyCurrentShieldAjaxRequestWithQuery( $query, $request, false );

		$this->assertFalse( ( new CaptureAjaxActionTransportConsistencyTestDouble() )->canRunForTest() );
	}
}

class CaptureAjaxActionTransportConsistencyTestDouble extends CaptureAjaxAction {

	public function canRunForTest() :bool {
		return $this->canRun();
	}

	public function buildIssuePayloadForTest() :array {
		return $this->buildAjaxIssuePayload();
	}
}
