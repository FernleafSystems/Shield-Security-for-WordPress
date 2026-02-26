<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay\BaseFullPageDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class FullPageDisplayPayloadSuccessContractTest extends BaseUnitTest {

	public function test_is_success_uses_payload_value_when_present() :void {
		$action = new FullPageDisplayPayloadSuccessTestDouble();
		$action->setPayloadForTest( [ 'success' => false ] );
		$action->setLegacySuccessForTest( true );

		$this->assertFalse( $action->isSuccessForTest() );
		$this->assertSame( 403, $action->responseCodeForTest() );
	}

	public function test_is_success_returns_false_when_payload_success_missing() :void {
		$action = new FullPageDisplayPayloadSuccessTestDouble();
		$action->setPayloadForTest( [ 'render_output' => 'ok' ] );
		$action->setLegacySuccessForTest( true );

		$this->assertFalse( $action->isSuccessForTest() );
		$this->assertSame( 403, $action->responseCodeForTest() );
	}

	public function test_response_code_is_success_when_payload_success_true() :void {
		$action = new FullPageDisplayPayloadSuccessTestDouble();
		$action->setPayloadForTest( [ 'success' => true ] );
		$action->setLegacySuccessForTest( false );

		$this->assertTrue( $action->isSuccessForTest() );
		$this->assertSame( 200, $action->responseCodeForTest() );
	}
}

class FullPageDisplayPayloadSuccessTestDouble extends BaseFullPageDisplay {

	public const SLUG = 'full_page_display_payload_success_test';

	public function setPayloadForTest( array $payload ) :void {
		$this->response()->setPayload( $payload );
	}

	public function setLegacySuccessForTest( bool $success ) :void {
		$this->response()->success = $success;
	}

	public function isSuccessForTest() :bool {
		return $this->isSuccess();
	}

	public function responseCodeForTest() :int {
		return $this->getResponseCode();
	}

	protected function exec() {
	}
}
