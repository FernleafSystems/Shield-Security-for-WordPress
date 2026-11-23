<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\DashboardLiveMonitorSetState,
	Actions\SecurityAdminAuthClear,
	CaptureAjaxAction,
	CapturePluginAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * A request refused at an entry point for a bad nonce doesn't echo back the action or its request data.
 */
class EntryPointNonceFailureIntegrationTest extends ShieldIntegrationTestCase {

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

	public function test_non_ajax_invalid_nonce_does_not_leak_slug_or_request_data() :void {
		$request = ActionData::Build( SecurityAdminAuthClear::class, false, [
			'leak_marker' => 'nonce-leak-marker',
		] );
		$request[ ActionData::FIELD_NONCE ] = 'invalid_nonce';
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-admin/admin.php?page=shield',
			],
			[],
			$request,
			[
				'path'       => '/wp-admin/admin.php',
				'wp_is_ajax' => false,
			]
		);

		$filter = static function () {
			return static function ( $message, $title = '', $args = [] ) :void {
				throw new EntryPointNonceFailureWpDieException(
					\is_array( $args ) ? $args : []
				);
			};
		};
		\add_filter( 'wp_die_handler', $filter );

		try {
			$capture = new EntryPointNonceFailurePluginCaptureTestDouble();
			$this->assertTrue( $capture->canRunForTest() );
			$capture->runForTest();
			$this->fail( 'Expected wp_die() for invalid non-AJAX nonce.' );
		}
		catch ( EntryPointNonceFailureWpDieException $e ) {
			$args = $e->args();
			$this->assertSame( CapturePluginAction::WP_DIE_INVALID_NONCE_CODE, $args[ 'code' ] ?? '' );
			$this->assertSame( CapturePluginAction::WP_DIE_INVALID_NONCE_STATUS, $args[ 'response' ] ?? 0 );
			$this->assertArrayNotHasKey( 'action_data', $args );
			$this->assertArrayNotHasKey( ActionData::FIELD_NONCE, $args );
			$this->assertStringNotContainsString( 'nonce-leak-marker', (string)\wp_json_encode( $args ) );
		}
		finally {
			\remove_filter( 'wp_die_handler', $filter );
		}
	}

	public function test_ajax_invalid_nonce_is_refused_without_leaking_request_data() :void {
		$request = ActionData::Build( DashboardLiveMonitorSetState::class, true, [
			'leak_marker' => 'nonce-leak-marker',
		] );
		$request[ ActionData::FIELD_NONCE ] = 'invalid_nonce';
		$this->applyCurrentShieldAjaxRequest( $request, true );

		$capture = new EntryPointNonceFailureAjaxCaptureTestDouble();
		$this->assertTrue( $capture->canRunForTest() );
		$payload = $capture->buildIssuePayloadForTest();

		$this->assertSame( 401, $payload[ 'status_code' ] ?? 0 );
		$this->assertFalse( (bool)( $payload[ 'success' ] ?? true ) );
		$this->assertStringNotContainsString( 'nonce-leak-marker', (string)\wp_json_encode( $payload ) );
		$this->assertStringNotContainsString( DashboardLiveMonitorSetState::SLUG, (string)\wp_json_encode( $payload[ 'data' ] ?? [] ) );
	}
}

class EntryPointNonceFailurePluginCaptureTestDouble extends CapturePluginAction {

	public function canRunForTest() :bool {
		return $this->canRun();
	}

	public function runForTest() :void {
		$this->theRun();
	}
}

class EntryPointNonceFailureAjaxCaptureTestDouble extends CaptureAjaxAction {

	public function canRunForTest() :bool {
		return $this->canRun();
	}

	public function buildIssuePayloadForTest() :array {
		return $this->buildAjaxIssuePayload();
	}
}

class EntryPointNonceFailureWpDieException extends \RuntimeException {

	private array $args;

	public function __construct( array $args ) {
		parent::__construct();
		$this->args = $args;
	}

	public function args() :array {
		return $this->args;
	}
}
