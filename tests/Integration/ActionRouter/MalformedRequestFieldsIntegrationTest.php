<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\FileDownload,
	Actions\FullPageDisplay\DisplayReport,
	Actions\IpAnalyseAction,
	Actions\IpRuleDelete,
	Actions\LicenseHandshakeVerifyKeyless,
	Actions\LicenseScheduleCheck,
	Actions\MfaEmailAutoLogin,
	Actions\MfaEmailSendIntent,
	Actions\MfaRemoveAll,
	Actions\PluginMarkTourFinished,
	Actions\PluginSetTracking,
	Actions\ScansFileLockerAction,
	CaptureAjaxAction,
	Exceptions\ActionException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

/**
 * Request fields with the wrong shape are refused as a controlled ActionException: never a TypeError, never coerced
 * into another record, and never read as "yes".
 */
class MalformedRequestFieldsIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];

	private $trackingSnapshot;

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->trackingSnapshot = $this->requireController()->opts->optGet( 'enable_tracking' );
		$this->loginAsSecurityAdmin();
	}

	public function tear_down() {
		$this->requireController()->opts->optSet( 'enable_tracking', $this->trackingSnapshot );
		\wp_clear_scheduled_hook( $this->requireController()->prefix( 'adhoc_cron_license_check' ) );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_tracking_disagreement_switches_tracking_off() :void {
		$opts = $this->requireController()->opts;
		$opts->optSet( 'enable_tracking', 'Y' );

		$this->process( PluginSetTracking::class, [ 'agree' => 'false' ] );
		$this->assertSame( 'N', $opts->optGet( 'enable_tracking' ) );

		$this->process( PluginSetTracking::class, [ 'agree' => 'true' ] );
		$this->assertSame( 'Y', $opts->optGet( 'enable_tracking' ) );
	}

	public function test_malformed_tracking_answer_changes_nothing() :void {
		$opts = $this->requireController()->opts;
		$opts->optSet( 'enable_tracking', 'N' );

		$this->assertRefused( PluginSetTracking::class, [ 'agree' => [ 'true' ] ], 'agree' );
		$this->assertSame( 'N', $opts->optGet( 'enable_tracking' ) );
	}

	/**
	 * @dataProvider malformedRequests
	 */
	public function test_malformed_field_is_refused( string $action, array $data, string $field ) :void {
		$this->assertRefused( $action, $data, $field );
	}

	public function malformedRequests() :array {
		return [
			'IP rule ID that PHP casts to 1'         => [ IpRuleDelete::class, [ 'rid' => [ '999' ] ], 'rid' ],
			'MFA removal user ID that casts to 1'    => [ MfaRemoveAll::class, [ 'user_id' => [ '999' ] ], 'user_id' ],
			'file lock confirmation sent as "false"' => [ ScansFileLockerAction::class, [ 'confirmed' => 'false', 'rid' => 1, 'file_action' => 'accept' ], 'confirmed' ],
			'file lock ID that casts to 1'           => [ ScansFileLockerAction::class, [ 'confirmed' => '1', 'rid' => [ '999' ], 'file_action' => 'accept' ], 'rid' ],
			'IP action sent as true'                 => [ IpAnalyseAction::class, [ 'ip' => '203.0.113.9', 'ip_action' => true ], 'ip_action' ],
			'email login nonce as a list'            => [ MfaEmailAutoLogin::class, [ 'user_id' => 1, 'login_nonce' => [ 'x' ], 'icwp_wpsf_email_otp' => 'x' ], 'login_nonce' ],
			'email login redirect as a list'         => [ MfaEmailAutoLogin::class, [ 'user_id' => 1, 'login_nonce' => 'x', 'redirect_to' => [ 'x' ], 'icwp_wpsf_email_otp' => 'x' ], 'redirect_to' ],
			'email login user ID as a list'          => [ MfaEmailAutoLogin::class, [ 'user_id' => [ '1' ], 'login_nonce' => 'x', 'icwp_wpsf_email_otp' => 'x' ], 'user_id' ],
			'email code resend nonce as a list'      => [ MfaEmailSendIntent::class, [ 'wp_user_id' => 1, 'login_nonce' => [ 'x' ] ], 'login_nonce' ],
			'licence handshake nonce as a list'      => [ LicenseHandshakeVerifyKeyless::class, [ 'nonce' => [ 'x' ] ], 'nonce' ],
			'report link ID as a list'               => [ DisplayReport::class, [ 'report_unique_id' => [ 'x' ] ], 'report_unique_id' ],
		];
	}

	/**
	 * @dataProvider downloadCategoriesTakingARecordId
	 */
	public function test_download_record_id_that_casts_to_1_is_refused( string $category ) :void {
		$payload = $this->process( FileDownload::class, [
			'download_category' => $category,
			'rid'               => [ '999' ],
		] );

		$this->assertFalse( (bool)( $payload[ 'success' ] ?? true ) );
		$this->assertSame( 'Invalid request field: rid', $payload[ 'message' ] ?? '' );
	}

	public function downloadCategoriesTakingARecordId() :array {
		return [
			'file lock'  => [ 'filelocker' ],
			'scan file'  => [ 'scan_file' ],
			'report PDF' => [ 'report_download_pdf' ],
		];
	}

	public function test_licence_check_ignores_a_malformed_delay_hint() :void {
		\wp_set_current_user( 0 );
		$payload = $this->process( LicenseScheduleCheck::class, [ 'delay' => [ 'x' ] ] );
		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
	}

	public function test_licence_check_accepts_a_delay_sent_as_text() :void {
		\wp_set_current_user( 0 );
		$payload = $this->process( LicenseScheduleCheck::class, [ 'delay' => '120' ] );
		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
	}

	/**
	 * A fault inside an action reached over AJAX gets a controlled response rather than a fatal error.
	 * PluginMarkTourFinished still passes its field to a typed parameter unchecked (SHI-2721), which makes it
	 * a real fault to send through the entry point.
	 */
	public function test_ajax_entry_point_contains_a_fault_in_the_action() :void {
		$request = ActionData::Build( PluginMarkTourFinished::class );
		$request[ 'tour_key' ] = [ 'not', 'text' ];
		$this->applyCurrentShieldAjaxRequest( $request, true );

		$payload = ( new MalformedFieldsAjaxCaptureTestDouble() )->buildIssuePayloadForTest();

		$this->assertSame( 500, $payload[ 'status_code' ] ?? null );
		$this->assertFalse( (bool)( $payload[ 'success' ] ?? true ) );
	}

	private function process( string $action, array $data ) :array {
		return ( new ActionProcessor() )->processAction( $action, $data )->payload();
	}

	private function assertRefused( string $action, array $data, string $field = '' ) :void {
		try {
			$this->process( $action, $data );
			$this->fail( 'Expected the malformed request to be refused.' );
		}
		catch ( ActionException $e ) {
			if ( $field !== '' ) {
				$this->assertSame( 'Invalid request field: '.$field, $e->getMessage(), 'Refused for another reason.' );
			}
		}
	}
}

class MalformedFieldsAjaxCaptureTestDouble extends CaptureAjaxAction {

	public function buildIssuePayloadForTest() :array {
		return $this->buildAjaxIssuePayload();
	}
}
