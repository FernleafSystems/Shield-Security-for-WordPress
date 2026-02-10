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

		// Get ConfigurationVO, remove premium flag, then persist back via __set → getRawData().
		// (cfg->configuration creates a NEW object each access — direct property assignment is lost)
		$configuration = $con->cfg->configuration;
		$optionsDef = $configuration->options;
		$originalPremium = $optionsDef['sec_admin_users']['premium'] ?? false;
		$optionsDef['sec_admin_users']['premium'] = false;
		$configuration->options = $optionsDef;
		$con->cfg->configuration = $configuration;

		// Set via the proper API — optSet() writes directly without a premium check.
		$con->opts->optSet( 'sec_admin_users', [ 'secadmin_testuser' ] );

		$this->assertTrue( $this->secAdmin()->isRegisteredSecAdminUser( $user ),
			'User in sec_admin_users should be a registered sec admin' );

		// Cleanup: restore premium flag and clear the value
		$configuration = $con->cfg->configuration;
		$optionsDef = $configuration->options;
		$optionsDef['sec_admin_users']['premium'] = $originalPremium;
		$configuration->options = $optionsDef;
		$con->cfg->configuration = $configuration;
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
