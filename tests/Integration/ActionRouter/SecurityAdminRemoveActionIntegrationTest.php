<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\SecurityAdminRemove
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\PluginAdminRouteRuntime;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class SecurityAdminRemoveActionIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator( [
			'user_login' => 'secadmin_remove_tester',
		] );
	}

	public function test_remove_action_clears_security_admin_state() :void {
		$snapshot = $this->snapshotSelectedOptions( [
			'admin_access_key',
			'sec_admin_users',
		] );

		try {
			$con = $this->requireController();
			$con->opts
				->optSet( 'admin_access_key', \wp_hash_password( 'remove-me-123' ) )
				->optSet( 'sec_admin_users', [ 'secadmin_remove_tester' ] )
				->store();

			$payload = ( new PluginAdminRouteRuntime() )->processActionPayloadWithAdminBypass(
				SecurityAdminRemove::SLUG,
				ActionData::Build( SecurityAdminRemove::class, false, [
					'quietly' => 1,
				] )
			);

			$this->assertTrue( (bool)( $payload[ 'success' ] ?? false ) );
			$this->assertSame( '', (string)$con->opts->optGet( 'admin_access_key' ) );
			$this->assertSame( [], $con->opts->optGet( 'sec_admin_users' ) );
			$this->assertFalse( $con->comps->sec_admin->isEnabledSecAdmin() );
		}
		finally {
			$this->restoreSelectedOptions( $snapshot );
		}
	}
}
