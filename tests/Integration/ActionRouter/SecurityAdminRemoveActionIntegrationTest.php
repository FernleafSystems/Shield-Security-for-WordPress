<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\SecurityAdminRemove,
	Actions\SecurityAdminRequestRemoveByEmail,
	Exceptions\ActionException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\PluginAdminRouteRuntime;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class SecurityAdminRemoveActionIntegrationTest extends ShieldIntegrationTestCase {

	use LocalEmailCapture;

	private array $optionsSnapshot = [];

	private string $securityAdminLogin = '';

	public function set_up() {
		parent::set_up();
		$this->securityAdminLogin = 'secadmin_remove_'.\bin2hex( \random_bytes( 4 ) );
		$this->loginAsAdministrator( [
			'user_login' => $this->securityAdminLogin,
			'user_email' => $this->securityAdminLogin.'@example.test',
		] );
		$this->startLocalEmailCapture();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [
			'admin_access_key',
			'sec_admin_users',
			'allow_email_override',
		] );
	}

	public function tear_down() {
		$this->stopLocalEmailCapture();
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionsSnapshot );
		}
		parent::tear_down();
	}

	public function test_remove_action_clears_security_admin_state_and_sends_notification() :void {
		$con = $this->requireController();
		$this->seedSecurityAdminState();

		$payload = $this->processRemoveAction();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertSame( '', (string)$con->opts->optGet( 'admin_access_key' ) );
		$this->assertSame( [], $con->opts->optGet( 'sec_admin_users' ) );
		$this->assertFalse( $con->comps->sec_admin->isEnabledSecAdmin() );
		$this->assertCount( 1, $this->capturedMails() );
	}

	public function test_remove_action_ignores_tampered_quietly_parameter() :void {
		$con = $this->requireController();
		$this->seedSecurityAdminState();

		$payload = $this->processRemoveAction( [
			'quietly' => 1,
		] );

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertSame( '', (string)$con->opts->optGet( 'admin_access_key' ) );
		$this->assertFalse( $con->comps->sec_admin->isEnabledSecAdmin() );
		$this->assertCount( 1, $this->capturedMails() );
	}

	public function test_email_override_request_sends_confirmation_when_option_enabled() :void {
		$this->seedSecurityAdminState();
		$this->requireController()->opts
			->optSet( 'allow_email_override', 'Y' )
			->store();

		$payload = $this->processRequestRemoveByEmailAction();

		$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
		$this->assertCount( 1, $this->capturedMails() );
	}

	public function test_email_override_request_is_rejected_when_option_disabled() :void {
		$con = $this->requireController();
		$this->seedSecurityAdminState();
		$con->opts
			->optSet( 'allow_email_override', 'N' )
			->store();

		try {
			$this->processRequestRemoveByEmailAction();
			$this->fail( 'Expected disabled email override to reject the action.' );
		}
		catch ( ActionException $e ) {
			$this->assertCount( 0, $this->capturedMails() );
			$this->assertNotSame( '', (string)$con->opts->optGet( 'admin_access_key' ) );
		}
	}

	private function seedSecurityAdminState() :void {
		$this->requireController()->opts
			->optSet( 'admin_access_key', \wp_hash_password( 'remove-me-123' ) )
			->optSet( 'sec_admin_users', [ $this->securityAdminLogin ] )
			->store();
	}

	private function processRemoveAction( array $aux = [] ) :array {
		return ( new PluginAdminRouteRuntime() )->processActionPayloadWithAdminBypass(
			SecurityAdminRemove::SLUG,
			ActionData::Build( SecurityAdminRemove::class, false, $aux )
		);
	}

	private function processRequestRemoveByEmailAction() :array {
		return ( new PluginAdminRouteRuntime() )->processActionPayloadWithAdminBypass(
			SecurityAdminRequestRemoveByEmail::SLUG,
			ActionData::Build( SecurityAdminRequestRemoveByEmail::class, false )
		);
	}
}
