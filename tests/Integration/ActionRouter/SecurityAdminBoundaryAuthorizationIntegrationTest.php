<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	CaptureAjaxAction,
	CapturePluginAction,
	Utility\ExternalEntryGuard
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	SecurityAdminAuthClear,
	SecurityAdminRemove,
	SecurityAdminRequestRemoveByEmail
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\SecurityAdminFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\LocalEmailCapture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class SecurityAdminBoundaryAuthorizationIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use LocalEmailCapture;

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->enablePremiumCapabilities();
		$this->loginAsAdministrator();
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

	public function test_missing_nonce_blocks_direct_disable_without_mutating_security_admin_state() :void {
		$this->seedBoundaryState();

		$this->assertRefusedAtEntryPoint(
			SecurityAdminRemove::class,
			$this->transportWithoutNonce( SecurityAdminRemove::class )
		);

		$this->assertNotSame( '', (string)$this->requireController()->opts->optGet( 'admin_access_key' ) );
		$this->assertSame( [ \wp_get_current_user()->user_login ], $this->requireController()->opts->optGet( 'sec_admin_users' ) );
		$this->assertCount( 0, $this->capturedMails() );
	}

	public function test_missing_nonce_blocks_email_override_without_generating_mail() :void {
		$this->seedBoundaryState();
		$this->requireController()->opts
			->optSet( 'allow_email_override', 'Y' )
			->store();

		$this->assertRefusedAtEntryPoint(
			SecurityAdminRequestRemoveByEmail::class,
			$this->transportWithoutNonce( SecurityAdminRequestRemoveByEmail::class )
		);

		$this->assertCount( 0, $this->capturedMails() );
		$this->assertNotSame( '', (string)$this->requireController()->opts->optGet( 'admin_access_key' ) );
	}

	public function test_invalid_nonce_and_user_action_override_do_not_clear_active_session() :void {
		$builder = new SecurityAdminFixtureBuilder();
		$seeded = $builder->seed( SecurityAdminFixtureBuilder::SCENARIO_ACTIVE_SESSION );

		try {
			$before = $builder->inspect( $seeded[ 'state' ] );
			$this->assertTrue( $before[ 'current' ][ 'session_active' ] );

			$invalidTransport = $this->canonicalShieldTransportFor( SecurityAdminAuthClear::class );
			$invalidTransport[ ActionData::FIELD_NONCE ] = 'invalid-security-admin-nonce';
			// Request-supplied override metadata has no effect: only the entry point decides.
			$invalidTransport[ 'action_overrides' ] = [
				'is_nonce_verify_required' => false,
			];
			$this->assertRefusedAtEntryPoint( SecurityAdminAuthClear::class, $invalidTransport );

			$after = $builder->inspect( $seeded[ 'state' ] );
			$this->assertTrue( $after[ 'current' ][ 'session_active' ] );
			$this->assertGreaterThan( 0, $after[ 'current' ][ 'secadmin_at' ] );
		}
		finally {
			$builder->cleanup( $seeded[ 'state' ] );
		}
	}

	private function seedBoundaryState() :void {
		$this->requireController()->opts
			->optSet( 'admin_access_key', \wp_hash_password( 'boundary-pin-123' ) )
			->optSet( 'sec_admin_users', [ \wp_get_current_user()->user_login ] )
			->optSet( 'allow_email_override', 'N' )
			->store();
	}

	/**
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction> $actionClass
	 * @return array<string,string>
	 */
	private function transportWithoutNonce( string $actionClass ) :array {
		$transport = $this->canonicalShieldTransportFor( $actionClass );
		unset( $transport[ ActionData::FIELD_NONCE ] );
		return $transport;
	}

	/**
	 * Sends the request through the entry point that would really receive it and asserts it is refused there.
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction> $actionClass
	 * @param array<string,mixed> $transport
	 */
	private function assertRefusedAtEntryPoint( string $actionClass, array $transport ) :void {
		$snapshot = $this->snapshotCurrentRequestState();
		try {
			if ( isset( ExternalEntryGuard::DeclaredAccess( $actionClass )[ ExternalEntryGuard::ENTRY_ORDINARY ] ) ) {
				$this->applyCurrentRequestState(
					[
						'REQUEST_METHOD' => 'GET',
						'REQUEST_URI'    => '/wp-admin/admin.php',
					],
					$transport,
					[],
					[
						'wp_is_ajax'        => false,
						'is_security_admin' => false,
					]
				);
				$capture = new SecurityAdminBoundaryPluginCaptureTestDouble();
				$this->assertTrue( $capture->canRunForTest() );
				try {
					$capture->runForTest();
					$this->fail( 'Expected the ordinary route to refuse '.$actionClass );
				}
				catch ( \WPDieException $e ) {
					$this->assertSame( 'Unexpected data. Please try again.', $e->getMessage() );
				}
			}
			else {
				$this->applyCurrentShieldAjaxRequest( $transport, false );
				$payload = ( new SecurityAdminBoundaryAjaxCaptureTestDouble() )->buildIssuePayloadForTest();
				$this->assertSame( 401, $payload[ 'status_code' ] ?? 0 );
				$this->assertFalse( (bool)( $payload[ 'success' ] ?? true ) );
			}
		}
		finally {
			$this->restoreCurrentRequestState( $snapshot );
		}
	}
}

class SecurityAdminBoundaryPluginCaptureTestDouble extends CapturePluginAction {

	public function canRunForTest() :bool {
		return $this->canRun();
	}

	public function runForTest() :void {
		$this->theRun();
	}
}

class SecurityAdminBoundaryAjaxCaptureTestDouble extends CaptureAjaxAction {

	public function buildIssuePayloadForTest() :array {
		return $this->buildAjaxIssuePayload();
	}
}
