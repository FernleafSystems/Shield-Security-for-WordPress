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

		// Write directly into OptsHandler::$values to bypass premium guard in optGet()
		$ref = new \ReflectionProperty( \get_class( $con->opts ), 'values' );
		$ref->setAccessible( true );
		$values = $ref->getValue( $con->opts );
		$values['sec_admin_users'] = [ 'secadmin_testuser' ];
		$ref->setValue( $con->opts, $values );

		// Temporarily remove the 'premium' flag from the option definition so optGet()
		// doesn't reset the value when there's no active license (CI environment).
		$optionsDef = $con->cfg->configuration->options;
		$originalPremium = $optionsDef['sec_admin_users']['premium'] ?? false;
		$optionsDef['sec_admin_users']['premium'] = false;
		$con->cfg->configuration->options = $optionsDef;

		$this->assertTrue( $this->secAdmin()->isRegisteredSecAdminUser( $user ),
			'User in sec_admin_users should be a registered sec admin' );

		// Cleanup
		$optionsDef['sec_admin_users']['premium'] = $originalPremium;
		$con->cfg->configuration->options = $optionsDef;
		$values['sec_admin_users'] = [];
		$ref->setValue( $con->opts, $values );
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
