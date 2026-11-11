<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	CaptureAjaxAction
};

/**
 * Asserts that an AJAX request for an action, built as the UI builds it but without a valid nonce for that
 * action, is refused by the AJAX entry point before the action runs.
 *
 * The using class must also use CurrentRequestFixture.
 */
trait ExternalEntryRefusalAssertions {

	/**
	 * @param class-string $actionClass
	 */
	private function assertAjaxRequestRefused( string $actionClass, array $data = [], string $nonce = '', bool $isSecurityAdmin = true ) :void {
		$snapshot = $this->snapshotCurrentRequestState();
		try {
			$request = ActionData::Build( $actionClass, true, $data );
			$request[ ActionData::FIELD_NONCE ] = $nonce;
			$this->applyCurrentShieldAjaxRequest( $request, $isSecurityAdmin );

			$capture = new ExternalEntryRefusalAjaxCaptureTestDouble();
			$this->assertTrue( $capture->canRunForTest(), $actionClass.' should be declared for AJAX.' );
			$payload = $capture->buildIssuePayloadForTest();
			$this->assertSame( 401, $payload[ 'status_code' ] ?? 0 );
			$this->assertFalse( (bool)( $payload[ 'success' ] ?? true ) );
		}
		finally {
			$this->restoreCurrentRequestState( $snapshot );
		}
	}
}

class ExternalEntryRefusalAjaxCaptureTestDouble extends CaptureAjaxAction {

	public function canRunForTest() :bool {
		return $this->canRun();
	}

	public function buildIssuePayloadForTest() :array {
		return $this->buildAjaxIssuePayload();
	}
}
