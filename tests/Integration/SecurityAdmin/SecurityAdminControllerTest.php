<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Restrictions\{
	Plugins,
	Posts,
	Themes,
	Users,
	WpOptions
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\SecurityAdminController;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class SecurityAdminControllerTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private function secAdmin() :SecurityAdminController {
		return $this->requireController()->comps->sec_admin;
	}

	public function test_no_pin_means_sec_admin_disabled() {
		$con = $this->requireController();

		$con->opts->optSet( 'admin_access_key', '' );

		$this->assertFalse( $this->secAdmin()->isEnabledSecAdmin(),
			'Security Admin should be disabled when no PIN is set' );
	}

	public function test_with_pin_means_sec_admin_enabled() {
		$con = $this->requireController();

		$con->opts->optSet( 'admin_access_key', \wp_hash_password( 'test-pin-123' ) );

		$this->assertTrue( $this->secAdmin()->isEnabledSecAdmin(),
			'Security Admin should be enabled when PIN is set' );

		$con->opts->optSet( 'admin_access_key', '' );
	}

	public function test_registered_sec_admin_user_detection() {
		$con = $this->requireController();
		$userId = self::factory()->user->create( [
			'role'       => 'administrator',
			'user_login' => 'secadmin_testuser',
		] );
		$user = \get_user_by( 'id', $userId );
		$snapshot = $this->snapshotSelectedOptions( [ 'sec_admin_users' ] );

		try {
			$this->enablePremiumCapabilities();
			$con->opts
				->optSet( 'sec_admin_users', [ 'secadmin_testuser' ] )
				->store();

			$this->assertTrue( $this->secAdmin()->isRegisteredSecAdminUser( $user ),
				'User in sec_admin_users should be a registered sec admin' );
		}
		finally {
			$this->restoreSelectedOptions( $snapshot );
		}
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

	public function test_persistent_security_admin_does_not_require_temporary_session_timestamp() :void {
		$con = $this->requireController();
		$userId = $this->loginAsAdministrator( [
			'user_login' => 'persistent_secadmin',
		] );
		$user = \get_user_by( 'id', $userId );
		$snapshot = $this->snapshotSelectedOptions( [
			'admin_access_key',
			'admin_access_timeout',
			'sec_admin_users',
		] );

		try {
			$this->enablePremiumCapabilities();
			$con->opts
				->optSet( 'admin_access_key', \wp_hash_password( 'persistent-pin-123' ) )
				->optSet( 'admin_access_timeout', 1 )
				->optSet( 'sec_admin_users', [ 'persistent_secadmin' ] )
				->store();
			$con->this_req->is_security_admin = false;
			unset( $con->this_req->session );

			$this->assertTrue( $this->secAdmin()->isRegisteredSecAdminUser( $user ) );
			$this->assertTrue( $this->secAdmin()->isCurrentUserRegisteredSecAdmin() );
			$this->assertTrue( $this->secAdmin()->isCurrentlySecAdmin() );
			$this->assertSame( 0, $this->secAdmin()->getSecAdminTimeRemaining() );
			$this->assertFalse( $this->secAdmin()->hasActiveSession() );
		}
		finally {
			$this->restoreSelectedOptions( $snapshot );
			unset( $con->this_req->session );
		}
	}

	public function test_security_admin_restriction_zones_block_owned_non_security_admin_behaviour() :void {
		$con = $this->requireController();
		$this->loginAsAdministrator();
		$snapshot = $this->snapshotSelectedOptions( [
			'admin_access_restrict_options',
			'admin_access_restrict_admin_users',
			'admin_access_restrict_plugins',
			'admin_access_restrict_themes',
			'admin_access_restrict_posts',
		] );
		$denyPluginAdmin = static fn() :bool => false;
		$filter = $con->prefix( 'is_plugin_admin' );

		try {
			$con->opts
				->optSet( 'admin_access_restrict_options', 'Y' )
				->optSet( 'admin_access_restrict_admin_users', 'Y' )
				->optSet( 'admin_access_restrict_plugins', [ 'install_plugins' ] )
				->optSet( 'admin_access_restrict_themes', [ 'update_themes' ] )
				->optSet( 'admin_access_restrict_posts', [ 'edit' ] )
				->store();
			$con->this_req->is_security_admin = false;
			\add_filter( $filter, $denyPluginAdmin, 1000 );

			$this->assertSame( 'old-title', ( new WpOptions() )->blockOptionsSaves( 'new-title', 'blogname', 'old-title' ) );
			$this->assertFalse( ( new Plugins() )->removeCapabilities( [ 'install_plugins' => true ], [], [ 'install_plugins' ] )[ 'install_plugins' ] );
			$this->assertFalse( ( new Themes() )->removeCapabilities( [ 'update_themes' => true ], [], [ 'update_themes' ] )[ 'update_themes' ] );
			$this->assertFalse( ( new Posts() )->removeCapabilities( [ 'edit_posts' => true ], [], [ 'edit_posts' ] )[ 'edit_posts' ] );
			$this->assertArrayNotHasKey(
				'administrator',
				( new Users() )->restrictEditableRoles( [
					'administrator' => [],
					'editor'        => [],
				] )
			);
			$requestSnapshot = $this->snapshotCurrentRequestState();
			try {
				$this->applyCurrentRequestState(
					[ 'REQUEST_METHOD' => 'POST' ],
					[],
					[ 'role' => 'administrator' ],
					[ 'is_security_admin' => false ]
				);
				$userCaps = ( new Users() )->restrictAdminUserChanges(
					[ 'create_users' => true ],
					[],
					[ 'create_users' ]
				);
				$this->assertFalse( $userCaps[ 'create_users' ] );
			}
			finally {
				$this->restoreCurrentRequestState( $requestSnapshot );
			}
		}
		finally {
			\remove_filter( $filter, $denyPluginAdmin, 1000 );
			$this->restoreSelectedOptions( $snapshot );
		}
	}

	public function test_security_admin_context_bypasses_wp_option_restriction() :void {
		$con = $this->requireController();
		$snapshot = $this->snapshotSelectedOptions( [ 'admin_access_restrict_options' ] );
		$bypassPluginAdmin = static fn() :bool => true;
		$filter = $con->prefix( 'bypass_is_plugin_admin' );

		try {
			$con->opts
				->optSet( 'admin_access_restrict_options', 'Y' )
				->store();
			$con->this_req->is_security_admin = true;
			\add_filter( $filter, $bypassPluginAdmin, 1000 );

			$this->assertSame( 'new-title', ( new WpOptions() )->blockOptionsSaves( 'new-title', 'blogname', 'old-title' ) );
		}
		finally {
			\remove_filter( $filter, $bypassPluginAdmin, 1000 );
			$this->restoreSelectedOptions( $snapshot );
		}
	}
}
