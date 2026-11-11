<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionNonce,
	ActionProcessor,
	Actions\AjaxBatchRequests,
	Actions\DashboardLiveMonitorSetState,
	Actions\MainWP\ServerActions\MainwpServerClientActionHandler,
	Actions\PluginBadgeClose,
	Actions\PluginSetOpt,
	CaptureAjaxAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\DashboardLiveMonitorPreference;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

/**
 * AJAX requests, batch items and MainWP site operations pass the same declared-access boundary as the
 * ordinary route, and refused requests are recorded.
 */
class AjaxEntryGuardIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private const OPT = 'email_can_send_verified_at';

	private array $requestSnapshot = [];

	private $optSnapshot;

	private array $events = [];

	private $eventListener;

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->optSnapshot = $this->requireController()->opts->optGet( self::OPT );
		$this->requireController()->opts->optSet( self::OPT, 0 );

		$this->events = [];
		$this->eventListener = function ( $event, $meta = [] ) {
			$this->events[] = [ $event, $meta ];
		};
		\add_action( 'shield/event', $this->eventListener, 10, 2 );
	}

	public function tear_down() {
		\remove_action( 'shield/event', $this->eventListener, 10 );
		$this->requireController()->opts->optSet( self::OPT, $this->optSnapshot );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_undeclared_action_is_not_captured_over_ajax_and_is_recorded() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldAjaxRequest( ActionData::Build( PluginSetOpt::class, true, [
			'opt_key'   => self::OPT,
			'opt_value' => 12345,
		] ), true );

		$this->assertFalse( ( new AjaxEntryGuardCaptureTestDouble() )->canRunForTest() );
		$this->assertSame( 0, (int)$this->requireController()->opts->optGet( self::OPT ) );
		$this->assertRejectionRecorded( PluginSetOpt::SLUG, 'ajax' );
	}

	public function test_declared_action_with_another_actions_nonce_is_refused_before_it_runs() :void {
		$this->loginAsSecurityAdmin();
		( new DashboardLiveMonitorPreference() )->setCollapsed( false );
		$request = ActionData::Build( DashboardLiveMonitorSetState::class, true, [
			'is_collapsed' => true,
		] );
		$request[ ActionData::FIELD_NONCE ] = ActionNonce::Create( PluginBadgeClose::class );
		$this->applyCurrentShieldAjaxRequest( $request, true );

		$capture = new AjaxEntryGuardCaptureTestDouble();
		$this->assertTrue( $capture->canRunForTest() );
		$payload = $capture->buildIssuePayloadForTest();

		$this->assertSame( 401, $payload[ 'status_code' ] ?? 0 );
		$this->assertFalse( ( new DashboardLiveMonitorPreference() )->isCollapsed() );
		$this->assertRejectionRecorded( DashboardLiveMonitorSetState::SLUG, 'ajax' );
	}

	public function test_expired_session_gets_the_auth_refresh_response_instead_of_a_recorded_rejection() :void {
		$adminId = $this->loginAsSecurityAdmin();
		( new DashboardLiveMonitorPreference() )->setCollapsed( false );
		$request = ActionData::Build( DashboardLiveMonitorSetState::class, true, [
			'is_collapsed' => true,
		] );
		\wp_set_current_user( 0 );
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD'              => 'POST',
				'REQUEST_URI'                 => '/wp-admin/admin-ajax.php',
				'HTTP_X_SHIELD_AUTH_REFRESH' => '1',
			],
			[],
			$request,
			[
				'path'       => '/wp-admin/admin-ajax.php',
				'wp_is_ajax' => true,
			]
		);

		$payload = ( new AjaxEntryGuardCaptureTestDouble() )->buildIssuePayloadForTest();

		$this->assertTrue( (bool)( $payload[ 'data' ][ 'auth_refresh_required' ] ?? false ) );
		\wp_set_current_user( $adminId );
		$this->assertFalse( ( new DashboardLiveMonitorPreference() )->isCollapsed() );
		$this->assertSame( [], \array_filter( $this->events, fn( array $e ) :bool => $e[ 0 ] === 'action_request_rejected' ) );
	}

	public function test_declared_action_with_its_valid_nonce_runs_over_ajax() :void {
		$this->loginAsSecurityAdmin();
		( new DashboardLiveMonitorPreference() )->setCollapsed( false );
		$this->applyCurrentShieldAjaxRequest( ActionData::Build( DashboardLiveMonitorSetState::class, true, [
			'is_collapsed' => true,
		] ), true );

		$payload = ( new AjaxEntryGuardCaptureTestDouble() )->buildIssuePayloadForTest();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertTrue( ( new DashboardLiveMonitorPreference() )->isCollapsed() );
	}

	public function test_batch_item_for_undeclared_action_is_refused_even_with_its_own_valid_nonce() :void {
		$this->loginAsSecurityAdmin();
		$item = ActionData::Build( PluginSetOpt::class, true, [
			'opt_key'   => self::OPT,
			'opt_value' => 12345,
		] );

		$payload = ( new ActionProcessor() )->processAction( AjaxBatchRequests::SLUG, [
			'requests' => [
				[
					'id'      => 'set_opt',
					'request' => $item,
				],
			],
		] )->payload();

		$this->assertSame( AjaxBatchRequests::ERROR_ACTION_TRANSPORT_NOT_ALLOWED, $payload[ 'results' ][ 'set_opt' ][ 'error_code' ] ?? '' );
		$this->assertSame( 0, (int)$this->requireController()->opts->optGet( self::OPT ) );
		$this->assertRejectionRecorded( PluginSetOpt::SLUG, 'ajax' );
	}

	public function test_mainwp_site_operation_is_chosen_only_from_the_fixed_list() :void {
		$this->loginAsSecurityAdmin();

		$payload = ( new ActionProcessor() )->processAction( MainwpServerClientActionHandler::SLUG, [
			'client_site_id'                                => 1,
			MainwpServerClientActionHandler::FIELD_OPERATION => PluginSetOpt::SLUG,
			// The old browser-chosen action payload: it must have no effect.
			'client_site_action_data'                       => [
				'site_action_slug'   => PluginSetOpt::SLUG,
				'site_action_params' => [
					'opt_key'   => self::OPT,
					'opt_value' => 12345,
				],
			],
		] )->payload();

		$this->assertFalse( (bool)( $payload[ 'success' ] ?? true ) );
		$this->assertSame( 'Unsupported site action.', $payload[ 'message' ] ?? '' );
		$this->assertSame( 0, (int)$this->requireController()->opts->optGet( self::OPT ) );
	}

	public function test_mainwp_site_operation_from_the_list_is_not_refused_for_a_missing_nonce() :void {
		$this->loginAsSecurityAdmin();
		$this->applyCurrentShieldAjaxRequest( [], true );

		// As the AJAX request arrives: the handler's own nonce, but nothing for the operation it runs.
		try {
			$payload = ( new ActionProcessor() )->processAction( MainwpServerClientActionHandler::SLUG, [
				ActionData::FIELD_NONCE                          => ActionNonce::Create( MainwpServerClientActionHandler::class ),
				'client_site_id'                                => 999999,
				MainwpServerClientActionHandler::FIELD_OPERATION => 'sync',
			] )->payload();
			$this->assertStringNotContainsStringIgnoringCase( 'nonce', (string)( $payload[ 'message' ] ?? '' ) );
		}
		catch ( \Error $e ) {
			// MainWP isn't installed here, so reaching its code proves the operation ran past the nonce check.
			$this->assertStringContainsString( 'MainWP', $e->getMessage() );
		}
	}

	private function assertRejectionRecorded( string $slug, string $entry ) :void {
		foreach ( $this->events as [ $event, $meta ] ) {
			if ( $event === 'action_request_rejected'
				 && ( $meta[ 'audit_params' ][ 'action' ] ?? '' ) === $slug
				 && ( $meta[ 'audit_params' ][ 'entry' ] ?? '' ) === $entry ) {
				$this->assertTrue( true );
				return;
			}
		}
		$this->fail( \sprintf( 'Expected a rejection to be recorded for %s on %s.', $slug, $entry ) );
	}
}

class AjaxEntryGuardCaptureTestDouble extends CaptureAjaxAction {

	public function canRunForTest() :bool {
		return $this->canRun();
	}

	public function buildIssuePayloadForTest() :array {
		return $this->buildAjaxIssuePayload();
	}
}
