<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Rest;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	DashboardLiveMonitorSetState,
	FullPageDisplay\FullPageDisplayDynamic,
	Render,
	Render\Components\Reports\Components\ReportAreaChanges,
	Render\Components\Scans\Results\Wordpress,
	Render\FullPage\Block\BlockFirewall,
	Render\FullPage\Mfa\Components\LoginIntentFormShield,
	Render\FullPage\Mfa\ShieldLoginIntentPage,
	Render\FullPage\Report\SecurityReport,
	TestRestFetchRequests
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\ShieldPluginAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ShieldPluginActionProcessContractIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator();
		$this->requireController()->this_req->is_security_admin = true;
	}

	public function test_process_returns_payload_driven_envelope_for_valid_action() :void {
		$process = new ShieldPluginActionProcessTestDouble();
		$result = $process->processForTest( [
			'ex'      => TestRestFetchRequests::SLUG,
			'payload' => [],
		] );

		$this->assertArrayHasKey( 'success', $result );
		$this->assertTrue( (bool)$result[ 'success' ] );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertIsArray( $result[ 'data' ] );
		$this->assertArrayHasKey( 'success', $result[ 'data' ] );
		$this->assertSame( (bool)$result[ 'success' ], (bool)$result[ 'data' ][ 'success' ] );
		$this->assertArrayHasKey( 'page_reload', $result[ 'data' ] );
		$this->assertArrayHasKey( 'message', $result[ 'data' ] );
		$this->assertArrayHasKey( 'html', $result[ 'data' ] );
		$this->assertFalse( (bool)$result[ 'data' ][ 'page_reload' ] );
		$this->assertIsString( $result[ 'data' ][ 'message' ] );
		$this->assertIsString( $result[ 'data' ][ 'html' ] );
		$restData = $this->requireController()->opts->optGet( TestRestFetchRequests::OPT_KEY );
		$this->assertGreaterThan( 0, (int)( $restData[ TestRestFetchRequests::DATA_SUCCESS_TEST_AT ] ?? 0 ) );
	}

	public function test_process_rejects_unlisted_rest_action_without_mutating_state() :void {
		( new DashboardLiveMonitorPreference() )->setCollapsed( false );

		$result = ( new ShieldPluginActionProcessTestDouble() )->processForTest( [
			'ex'      => DashboardLiveMonitorSetState::SLUG,
			'payload' => [
				'is_collapsed' => true,
			],
		] );

		$this->assertFalse( (bool)$result[ 'success' ] );
		$this->assertFalse( (bool)( $result[ 'data' ][ 'success' ] ?? true ) );
		$this->assertFalse( ( new DashboardLiveMonitorPreference() )->isCollapsed() );
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
		$this->assertFalse( (bool)$result[ 'data' ][ 'page_reload' ] );
		$this->assertIsString( $result[ 'data' ][ 'message' ] );
		$this->assertIsString( $result[ 'data' ][ 'html' ] );
	}

	public function test_process_rejects_public_render_transport() :void {
		$result = ( new ShieldPluginActionProcessTestDouble() )->processForTest( [
			'ex'      => Render::SLUG,
			'payload' => [
				'render_action_slug' => ShieldLoginIntentPage::SLUG,
				'render_action_data' => [
					'msg_error' => '<img src=x onerror=alert(1)>',
				],
			],
		] );

		$this->assertFalse( (bool)$result[ 'success' ] );
		$this->assertFalse( (bool)( $result[ 'data' ][ 'success' ] ?? true ) );
	}

	public function test_process_rejects_blocked_full_page_transport() :void {
		$result = ( new ShieldPluginActionProcessTestDouble() )->processForTest( [
			'ex'      => FullPageDisplayDynamic::SLUG,
			'payload' => [
				'render_slug' => ShieldLoginIntentPage::SLUG,
				'render_data' => [],
			],
		] );

		$this->assertFalse( (bool)$result[ 'success' ] );
		$this->assertFalse( (bool)( $result[ 'data' ][ 'success' ] ?? true ) );
	}

	/**
	 * @dataProvider directRenderRestRequestProvider
	 */
	public function test_process_rejects_direct_render_action_slugs( string $renderSlug, array $payload ) :void {
		$result = ( new ShieldPluginActionProcessTestDouble() )->processForTest( [
			'ex'      => $renderSlug,
			'payload' => $payload,
		] );

		$this->assertFalse( (bool)$result[ 'success' ] );
		$this->assertFalse( (bool)( $result[ 'data' ][ 'success' ] ?? true ) );
	}

	public function directRenderRestRequestProvider() :array {
		$reportPayload = [
			'report' => [
				'type'       => 'info',
				'interval'   => 'daily',
				'start_at'   => 1,
				'end_at'     => 2,
				'areas'      => [
					'changes' => true,
				],
				'areas_data' => [
					'changes' => [
						'wordpress' => [
							'total' => 0,
						],
					],
				],
			],
		];

		return [
			[
				SecurityReport::SLUG,
				$reportPayload,
			],
			[
				ReportAreaChanges::SLUG,
				$reportPayload,
			],
			[
				BlockFirewall::SLUG,
				[
					'block_meta_data' => [
						'match_category'      => 'test',
						'match_request_param' => 'payload',
						'match_request_value' => 'blocked',
						'match_pattern'       => 'blocked',
					],
				],
			],
			[
				LoginIntentFormShield::SLUG,
				[
					'user_id'           => 1,
					'plain_login_nonce' => 'nonce',
					'rememberme'        => '',
				],
			],
			[
				Wordpress::SLUG,
				[
					'display_context' => 'actions_queue',
				],
			],
		];
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
