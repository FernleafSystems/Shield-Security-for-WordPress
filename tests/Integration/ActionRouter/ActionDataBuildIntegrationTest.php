<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionNonce,
	Actions\CaptureNotBot,
	Actions\DashboardLiveMonitorSetState,
	Actions\IpAutoUnblockShieldVisitor,
	Actions\TestRestFetchRequests
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * The request data built for an action carries what its ExternalAccess() declaration requires, and no more.
 */
class ActionDataBuildIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator();
	}

	public function test_ajax_action_with_nonce_proof_gets_its_nonce_and_no_rest_details() :void {
		$data = ActionData::Build( DashboardLiveMonitorSetState::class );

		$this->assertSame( DashboardLiveMonitorSetState::SLUG, $data[ ActionData::FIELD_EXECUTE ] );
		$this->assertTrue( ActionNonce::Verify( DashboardLiveMonitorSetState::class, $data[ ActionData::FIELD_NONCE ] ) );
		$this->assertNotSame( '', $data[ ActionData::FIELD_AJAXURL ] );
		$this->assertArrayNotHasKey( ActionData::FIELD_REST_URL, $data );
		$this->assertArrayNotHasKey( ActionData::FIELD_REST_NONCE, $data );
	}

	public function test_action_without_nonce_proof_gets_no_nonce() :void {
		$data = ActionData::Build( CaptureNotBot::class );

		$this->assertSame( CaptureNotBot::SLUG, $data[ ActionData::FIELD_EXECUTE ] );
		$this->assertArrayNotHasKey( ActionData::FIELD_NONCE, $data );
	}

	public function test_rest_action_gets_rest_details_with_its_nonce_in_the_url() :void {
		$data = ActionData::Build( TestRestFetchRequests::class );

		$this->assertNotSame( '', $data[ ActionData::FIELD_REST_NONCE ] );
		\parse_str( (string)\wp_parse_url( $data[ ActionData::FIELD_REST_URL ], \PHP_URL_QUERY ), $query );
		$this->assertSame( $data[ ActionData::FIELD_NONCE ], $query[ ActionData::FIELD_NONCE ] ?? null );
	}

	public function test_ordinary_form_data_has_no_ajax_details() :void {
		$data = ActionData::Build( IpAutoUnblockShieldVisitor::class, false );

		$this->assertTrue( ActionNonce::Verify( IpAutoUnblockShieldVisitor::class, $data[ ActionData::FIELD_NONCE ] ) );
		$this->assertArrayNotHasKey( ActionData::FIELD_AJAXURL, $data );
		$this->assertArrayNotHasKey( ActionData::FIELD_REST_URL, $data );
	}
}
