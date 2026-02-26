<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rest;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\OperatorModeSwitch;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\ShieldPluginAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ShieldPluginActionProcessContractIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator();
	}

	public function test_process_returns_payload_driven_envelope_for_valid_action() :void {
		$process = new ShieldPluginActionProcessTestDouble();
		$result = $process->processForTest( [
			'ex'      => OperatorModeSwitch::SLUG,
			'payload' => [
				'mode' => 'default',
			],
		] );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertIsArray( $result[ 'data' ] );
		$this->assertArrayHasKey( 'success', $result[ 'data' ] );
		$this->assertSame( (bool)$result[ 'success' ], (bool)$result[ 'data' ][ 'success' ] );
		$this->assertArrayHasKey( 'page_reload', $result[ 'data' ] );
		$this->assertArrayHasKey( 'message', $result[ 'data' ] );
		$this->assertArrayHasKey( 'html', $result[ 'data' ] );
	}

	public function test_process_returns_failure_envelope_for_action_exception() :void {
		$process = new ShieldPluginActionProcessTestDouble();
		$result = $process->processForTest( [
			'ex'      => 'definitely_invalid_action_slug',
			'payload' => [],
		] );

		$this->assertFalse( (bool)$result[ 'success' ] );
		$this->assertFalse( (bool)( $result[ 'data' ][ 'success' ] ?? true ) );
		$this->assertArrayHasKey( 'page_reload', $result[ 'data' ] );
		$this->assertArrayHasKey( 'message', $result[ 'data' ] );
		$this->assertArrayHasKey( 'html', $result[ 'data' ] );
	}
}

class ShieldPluginActionProcessTestDouble extends ShieldPluginAction {

	public function processForTest( array $params ) :array {
		$request = new \WP_REST_Request( 'POST', '/shield/v1/plugin_action' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$this->setWpRestRequest( $request );
		return $this->process();
	}
}

