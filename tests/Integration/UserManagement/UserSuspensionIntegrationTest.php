<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Suspend\{
	Idle,
	PasswordExpiry,
	Suspended,
	UserSuspendController
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class UserSuspensionIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'user_meta' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'enable_password_policies',
			'manual_suspend',
			'auto_password',
			'auto_idle_days',
			'auto_idle_roles',
			'pass_expire',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR' => '203.0.113.74',
			],
			[],
			[],
			[
				'ip'                       => '203.0.113.74',
				'ip_id'                    => IpID::UNKNOWN,
				'is_server_loopback'       => false,
				'request_bypasses_all_restrictions' => false,
			]
		);
	}

	public function tear_down() {
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		$this->restoreSelectedOptions( $this->optionSnapshot );
		\wp_set_current_user( 0 );

		parent::tear_down();
	}

	public function test_hard_suspension_sets_record_blocks_auth_and_unsuspends_with_events() :void {
		$admin = $this->createUser( 'administrator' );
		$target = $this->createUser( 'subscriber' );
		\wp_set_current_user( (int)$admin->ID );
		$this->configureSuspension( [
			'manual_suspend' => 'Y',
		] );
		$this->captureShieldEvents();

		$controller = new UserSuspendController();
		$controller->addRemoveHardSuspendUser( $target, true );
		$suspendedMeta = $this->requireController()->user_metas->for( $target );

		$this->assertGreaterThan( 0, $suspendedMeta->record->hard_suspended_at );
		$this->assertSame(
			$this->requireController()->prefix( 'hard-suspended' ),
			( new Suspended() )->checkUser( $target )->get_error_code()
		);

		$suspendedEvents = $this->getCapturedEventsByKey( 'user_hard_suspended' );
		$this->assertCount( 1, $suspendedEvents );
		$this->assertSame( $target->user_login, $suspendedEvents[ 0 ][ 'meta' ][ 'audit_params' ][ 'user_login' ] ?? '' );

		$controller->addRemoveHardSuspendUser( $target, false );
		$unsuspendedMeta = $this->requireController()->user_metas->for( $target );

		$this->assertSame( 0, $unsuspendedMeta->record->hard_suspended_at );
		$this->assertSame( $target, ( new Suspended() )->checkUser( $target ) );

		$unsuspendedEvents = $this->getCapturedEventsByKey( 'user_hard_unsuspended' );
		$this->assertCount( 1, $unsuspendedEvents );
		$this->assertSame( $target->user_login, $unsuspendedEvents[ 0 ][ 'meta' ][ 'audit_params' ][ 'user_login' ] ?? '' );
	}

	public function test_profile_submit_hard_suspension_destroys_target_sessions() :void {
		$target = $this->createUser( 'subscriber' );
		$this->configureSuspension( [
			'manual_suspend' => 'Y',
		] );

		$tokens = \WP_Session_Tokens::get_instance( (int)$target->ID );
		$tokens->create( \time() + \DAY_IN_SECONDS );
		$tokens->create( \time() + 2*\DAY_IN_SECONDS );
		$this->assertCount( 2, $tokens->get_all() );

		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-admin/user-edit.php',
			],
			[],
			[
				'shield_suspend_user' => 'Y',
			],
			[
				'path'                     => '/wp-admin/user-edit.php',
				'request_bypasses_all_restrictions' => false,
			]
		);

		( new UserSuspendController() )->handleUserSuspendOptionSubmit( (int)$target->ID );

		$this->assertCount( 0, $tokens->get_all() );
		$this->assertGreaterThan(
			0,
			$this->requireController()->user_metas->for( $target )->record->hard_suspended_at
		);
	}

	public function test_idle_suspension_blocks_only_configured_idle_roles() :void {
		$subscriber = $this->createUser( 'subscriber' );
		$editor = $this->createUser( 'editor' );
		$this->configureSuspension( [
			'auto_idle_days'  => 1,
			'auto_idle_roles' => [ 'subscriber' ],
		] );
		$this->setUserActivityAt( $subscriber, \time() - 2*\DAY_IN_SECONDS );
		$this->setUserActivityAt( $editor, \time() - 2*\DAY_IN_SECONDS );

		$blocked = ( new Idle() )->checkUser( $subscriber );
		$this->assertSame( $this->requireController()->prefix( 'pass-expired' ), $blocked->get_error_code() );
		$this->assertSame( $editor, ( new Idle() )->checkUser( $editor ) );

		$this->configureSuspension( [
			'auto_idle_days'  => 0,
			'auto_idle_roles' => [ 'subscriber' ],
		] );

		$this->assertFalse( ( new UserSuspendController() )->isSuspendAutoIdleEnabled() );
	}

	public function test_password_expiry_suspension_uses_shared_pass_expired_error_code_when_configured() :void {
		$user = $this->createUser( 'subscriber' );
		$this->configureSuspension( [
			'enable_password_policies' => 'Y',
			'auto_password'            => 'Y',
			'pass_expire'              => 1,
		] );
		$this->setUserActivityAt( $user, \time() - 2*\DAY_IN_SECONDS );

		$blocked = ( new PasswordExpiry() )->checkUser( $user );
		$this->assertSame( $this->requireController()->prefix( 'pass-expired' ), $blocked->get_error_code() );

		$this->configureSuspension( [
			'enable_password_policies' => 'Y',
			'auto_password'            => 'Y',
			'pass_expire'              => 0,
		] );

		$this->assertSame( $user, ( new PasswordExpiry() )->checkUser( $user ) );
	}

	private function configureSuspension( array $options = [] ) :void {
		$this->enablePremiumCapabilities( [ 'user_suspension', 'user_password_policies' ] );
		RuntimeTestState::restoreOptions( \array_merge( [
			'enable_password_policies' => 'N',
			'manual_suspend'           => 'N',
			'auto_password'            => 'N',
			'auto_idle_days'           => 0,
			'auto_idle_roles'          => [],
			'pass_expire'              => 0,
		], $options ) );
		$this->requireController()->this_req->request_bypasses_all_restrictions = false;
	}

	private function createUser( string $role ) :\WP_User {
		$userID = self::factory()->user->create( [
			'role'       => $role,
			'user_login' => 'sus-'.\substr( $role, 0, 3 ).'-'.\substr( \md5( \uniqid( '', true ) ), 0, 12 ),
			'user_pass'  => 'wZ8!nQ3#sL6@pR9$2026',
		] );
		if ( \is_wp_error( $userID ) ) {
			$this->fail( $userID->get_error_code() );
		}

		$user = \get_user_by( 'id', $userID );
		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertNotNull( $this->requireController()->user_metas->for( $user )->record );
		return $user;
	}

	private function setUserActivityAt( \WP_User $user, int $timestamp ) :void {
		$meta = $this->requireController()->user_metas->for( $user );
		$meta->record->first_seen_at = $timestamp;
		$meta->record->last_login_at = $timestamp;
		$meta->record->pass_started_at = $timestamp;
	}
}
