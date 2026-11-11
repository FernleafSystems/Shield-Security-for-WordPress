<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionNonce,
	Actions\CrowdsecResetEnrollment,
	Actions\FullPageDisplay\DisplayReport,
	Actions\IpAutoUnblockShieldVisitor,
	Actions\MfaCanEmailSendVerify,
	Actions\MfaLoginVerifyStep,
	Actions\PluginSetOpt,
	CapturePluginAction,
	Utility\ExternalEntryGuard
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\EmailDeliveryVerificationMailer;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

/**
 * The ordinary (non-AJAX) Shield action route admits only actions declared for it, and verifies the
 * declared proof before the action runs.
 */
class OrdinaryRouteEntryGuardIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private const ENROLLED_ID = 'shield-test-enrolled-id';

	private const SNAPSHOT_OPTS = [
		'email_can_send_verified_at',
		'email_can_send_verification_sent_at',
	];

	private array $requestSnapshot = [];

	private array $optsSnapshot = [];

	private $csAuthsSnapshot;

	public function set_up() {
		parent::set_up();
		$con = $this->requireController();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		foreach ( self::SNAPSHOT_OPTS as $opt ) {
			$this->optsSnapshot[ $opt ] = $con->opts->optGet( $opt );
			$con->opts->optSet( $opt, 0 );
		}
		$this->csAuthsSnapshot = \get_option( $con->prefix( 'cs_auths' ) );

		$store = $con->comps->crowdsec->getCApiStore();
		$store->storeAuth( \array_merge( $store->getAuth(), [
			'machine_enrolled' => true,
			'enrolled_id'      => self::ENROLLED_ID,
		] ) );
	}

	public function tear_down() {
		$con = $this->requireController();
		foreach ( $this->optsSnapshot as $opt => $value ) {
			$con->opts->optSet( $opt, $value );
		}
		\update_option( $con->prefix( 'cs_auths' ), $this->csAuthsSnapshot );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_undeclared_action_is_not_captured_even_with_its_own_valid_nonce() :void {
		$this->loginAsSecurityAdmin();
		$this->applyOrdinaryRequest( ActionData::Build( PluginSetOpt::class, false, [
			'opt_key'   => 'email_can_send_verified_at',
			'opt_value' => 12345,
		] ) );

		$capture = new OrdinaryRouteCaptureTestDouble();
		$this->assertFalse( $capture->canRunForTest() );

		$capture->execute();
		$this->assertSame( 0, (int)$this->requireController()->opts->optGet( 'email_can_send_verified_at' ) );
	}

	public function test_declared_command_without_nonce_is_rejected_before_it_runs() :void {
		$this->loginAsSecurityAdmin();
		$request = ActionData::Build( CrowdsecResetEnrollment::class, false );
		unset( $request[ ActionData::FIELD_NONCE ] );

		$this->assertRejectedWithoutEffect( $request );
	}

	public function test_declared_command_with_another_actions_nonce_is_rejected_before_it_runs() :void {
		$this->loginAsSecurityAdmin();
		$request = ActionData::Build( CrowdsecResetEnrollment::class, false );
		$request[ ActionData::FIELD_NONCE ] = ActionNonce::Create( MfaLoginVerifyStep::class );

		$this->assertRejectedWithoutEffect( $request );
	}

	public function test_declared_command_with_its_valid_nonce_runs() :void {
		$this->loginAsSecurityAdmin();
		$this->applyOrdinaryRequest( ActionData::Build( CrowdsecResetEnrollment::class, false ) );

		$capture = new OrdinaryRouteCaptureTestDouble();
		$this->assertTrue( $capture->canRunForTest() );
		$capture->runForTest();

		$this->assertSame( '', $this->enrolledId() );
	}

	public function test_declared_public_action_is_captured_without_nonce() :void {
		$request = ActionData::Build( DisplayReport::class, false, [
			'report_unique_id' => '00000000-0000-4000-8000-000000000000',
		] );
		unset( $request[ ActionData::FIELD_NONCE ] );
		$this->applyOrdinaryRequest( $request );

		$this->assertTrue( ( new OrdinaryRouteCaptureTestDouble() )->canRunForTest() );
	}

	public function test_logged_out_login_form_nonce_verifies_for_the_login_step() :void {
		\wp_set_current_user( 0 );
		$request = ActionData::Build( MfaLoginVerifyStep::class, false );
		$this->applyOrdinaryRequest( $request );

		( new ExternalEntryGuard() )->verifyProof( MfaLoginVerifyStep::SLUG, ExternalEntryGuard::ENTRY_ORDINARY, $request );
		$this->assertTrue( ( new OrdinaryRouteCaptureTestDouble() )->canRunForTest() );
	}

	public function test_block_page_unblock_form_nonce_verifies_for_the_visitor_unblock() :void {
		\wp_set_current_user( 0 );
		// Built exactly as the block page's auto-unblock form builds it, then posted natively.
		$request = ActionData::Build( IpAutoUnblockShieldVisitor::class );
		$this->applyOrdinaryRequest( $request, 'POST' );

		( new ExternalEntryGuard() )->verifyProof( IpAutoUnblockShieldVisitor::SLUG, ExternalEntryGuard::ENTRY_ORDINARY, $request );
		$this->assertTrue( ( new OrdinaryRouteCaptureTestDouble() )->canRunForTest() );
	}

	public function test_email_verification_link_sent_by_one_admin_verifies_for_the_recipient() :void {
		$this->loginAsAdministrator();
		$link = $this->sendVerificationEmailAndExtractLinkQuery();

		$this->loginAsAdministrator();
		$this->applyOrdinaryRequest( $link );
		$capture = new OrdinaryRouteCaptureTestDouble();
		$this->assertTrue( $capture->canRunForTest() );
		$capture->runForTest();

		$this->assertGreaterThan( 0, (int)$this->requireController()->opts->optGet( 'email_can_send_verified_at' ) );
	}

	public function test_email_verification_link_without_its_token_does_not_verify() :void {
		$this->loginAsAdministrator();
		$link = $this->sendVerificationEmailAndExtractLinkQuery();
		$link[ MfaCanEmailSendVerify::FIELD_TOKEN ] = \str_repeat( '0', 32 );

		$this->applyOrdinaryRequest( $link );
		( new OrdinaryRouteCaptureTestDouble() )->runForTest();

		$this->assertSame( 0, (int)$this->requireController()->opts->optGet( 'email_can_send_verified_at' ) );
	}

	private function sendVerificationEmailAndExtractLinkQuery() :array {
		\reset_phpmailer_instance();
		$this->assertTrue( ( new EmailDeliveryVerificationMailer() )->send() );

		$body = \html_entity_decode( (string)\tests_retrieve_phpmailer_instance()->get_sent()->body );
		$this->assertSame( 1, \preg_match( '#https?://[^\s"\'<>]*'.MfaCanEmailSendVerify::SLUG.'[^\s"\'<>]*#', $body, $matches ) );

		\parse_str( (string)\wp_parse_url( $matches[ 0 ], \PHP_URL_QUERY ), $query );
		$this->assertNotEmpty( $query[ MfaCanEmailSendVerify::FIELD_TOKEN ] ?? '' );
		return $query;
	}

	private function assertRejectedWithoutEffect( array $request ) :void {
		$this->applyOrdinaryRequest( $request );

		$capture = new OrdinaryRouteCaptureTestDouble();
		$this->assertTrue( $capture->canRunForTest() );

		try {
			$capture->runForTest();
			$this->fail( 'Expected the request to be rejected.' );
		}
		catch ( \WPDieException $e ) {
			$this->assertSame( 'Unexpected data. Please try again.', $e->getMessage() );
		}

		$this->assertSame( self::ENROLLED_ID, $this->enrolledId() );
	}

	private function enrolledId() :string {
		return (string)( $this->requireController()->comps->crowdsec->getCApiStore()->getAuth()[ 'enrolled_id' ] ?? '' );
	}

	private function applyOrdinaryRequest( array $request, string $method = 'GET' ) :void {
		if ( $method === 'POST' ) {
			$this->applyCurrentRequestState( [
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/',
			], [], $request );
		}
		else {
			$this->applyCurrentRequestState( [
				'REQUEST_URI' => '/wp-admin/?'.\http_build_query( $request ),
			], $request );
		}
	}
}

class OrdinaryRouteCaptureTestDouble extends CapturePluginAction {

	public function canRunForTest() :bool {
		return $this->canRun();
	}

	public function runForTest() :void {
		$this->theRun();
	}
}
