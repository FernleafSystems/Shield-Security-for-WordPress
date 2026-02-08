<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\SecurityAdminController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Tests SecurityAdminController: PIN-based enablement, registered admin
 * user detection, and session-based access control.
 */
class SecurityAdminControllerTest extends ShieldIntegrationTestCase {

	private function secAdmin() :SecurityAdminController {
		return $this->requireController()->comps->sec_admin;
	}

	public function test_no_pin_means_sec_admin_disabled() {
		$con = $this->requireController();

		// Ensure PIN is empty
		$con->opts->optSet( 'admin_access_key', '' );

		$this->assertFalse( $this->secAdmin()->isEnabledSecAdmin(),
			'Security Admin should be disabled when no PIN is set' );
	}

	public function test_with_pin_means_sec_admin_enabled() {
		$con = $this->requireController();

		// Set a PIN hash
		$con->opts->optSet( 'admin_access_key', \wp_hash_password( 'test-pin-123' ) );

		$this->assertTrue( $this->secAdmin()->isEnabledSecAdmin(),
			'Security Admin should be enabled when PIN is set' );

		// Cleanup
		$con->opts->optSet( 'admin_access_key', '' );
	}

	public function test_registered_sec_admin_user_detection() {
		$con = $this->requireController();

		$userId = self::factory()->user->create( [
			'role'       => 'administrator',
			'user_login' => 'secadmin_testuser',
		] );
		$user = \get_user_by( 'id', $userId );

		// Add the user to sec_admin_users
		$con->opts->optSet( 'sec_admin_users', [ 'secadmin_testuser' ] );

		$this->assertTrue( $this->secAdmin()->isRegisteredSecAdminUser( $user ),
			'User in sec_admin_users should be a registered sec admin' );

		// Cleanup
		$con->opts->optSet( 'sec_admin_users', [] );
	}

	public function test_non_registered_user_is_not_sec_admin() {
		$con = $this->requireController();

		$userId = self::factory()->user->create( [
			'role'       => 'administrator',
			'user_login' => 'regular_admin',
		] );
		$user = \get_user_by( 'id', $userId );

		$con->opts->optSet( 'sec_admin_users', [] );

		$this->assertFalse( $this->secAdmin()->isRegisteredSecAdminUser( $user ) );
	}

}
