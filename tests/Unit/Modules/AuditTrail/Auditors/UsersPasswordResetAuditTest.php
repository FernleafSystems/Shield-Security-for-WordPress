<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules {

	if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
		function shield_security_get_plugin() {
			return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
		}
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\AuditTrail\Auditors {

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Auditors\Users;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};
use FernleafSystems\Wordpress\Services\Core;

class UsersPasswordResetAuditTest extends BaseUnitTest {

	private PasswordResetAuditEventsRecorder $events;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->returnArg();
		Functions\when( 'is_wp_error' )->alias( static fn( $value ) :bool => $value instanceof \WP_Error );
		Functions\when( 'sanitize_text_field' )->alias(
			static fn( $value ) :string => \trim( \strip_tags( (string)$value ) )
		);
		Functions\when( 'sanitize_user' )->alias(
			static fn( string $value ) :string => \preg_replace( '/[^A-Za-z0-9_.@-]/', '', $value ) ?? ''
		);
		Functions\when( 'wp_unslash' )->alias(
			static fn( $value ) => \is_string( $value ) ? \stripslashes( $value ) : $value
		);

		$this->servicesSnapshot = ServicesState::snapshot();
		$this->events = new PasswordResetAuditEventsRecorder();

		UnitTestControllerFactory::install(
			new UnitTestPluginUrls(),
			null,
			(object)[
				'comps' => (object)[
					'events'       => $this->events,
					'activity_log' => new PasswordResetAuditActivityLogStub(),
				],
			]
		);
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_unknown_reset_request_logs_failure_with_requested_login_and_reason() :void {
		$this->installServices( [], [ 'user_login' => ' missing-user@example.test ' ] );
		$errors = new \WP_Error();

		( new Users() )->capturePasswordResetRequestErrors( $errors, false );

		$this->assertSame(
			[
				[
					'event' => 'user_password_reset_request_failed',
					'meta'  => [
						'audit_params' => [
							'requested_login' => 'missing-user@example.test',
							'reason'          => 'invalidcombo',
						],
					],
				],
			],
			$this->events->firedEvents
		);
	}

	public function test_explicit_reset_request_error_logs_failure_reason_codes() :void {
		$this->installServices( [], [ 'user_login' => 'blocked-user' ] );
		$errors = new \WP_Error();
		$errors->add( 'custom_blocked', 'Blocked.' );
		$errors->add( 'second_reason', 'Second.' );

		( new Users() )->capturePasswordResetRequestErrors( $errors, false );

		$this->assertSame( 'user_password_reset_request_failed', $this->events->firedEvents[ 0 ][ 'event' ] ?? '' );
		$this->assertSame(
			[
				'requested_login' => 'blocked-user',
				'reason'          => 'custom_blocked,second_reason',
			],
			$this->events->firedEvents[ 0 ][ 'meta' ][ 'audit_params' ] ?? []
		);
	}

	public function test_reset_disallowed_logs_failure_only_during_active_reset_request() :void {
		$user = $this->makeUser( 23, 'known-user' );
		$this->installServices( [ $user ], [ 'user_login' => 'known-user' ] );
		$auditor = new Users();

		$this->assertFalse( $auditor->capturePasswordResetDisallowed( false, $user->ID ) );
		$this->assertSame( [], $this->events->firedEvents );

		$auditor->capturePasswordResetRequestErrors( new \WP_Error(), $user );
		$this->assertFalse( $auditor->capturePasswordResetDisallowed( false, $user->ID ) );

		$this->assertSame( 'user_password_reset_request_failed', $this->events->firedEvents[ 0 ][ 'event' ] ?? '' );
		$this->assertSame(
			[
				'requested_login' => 'known-user',
				'reason'          => 'no_password_reset',
			],
			$this->events->firedEvents[ 0 ][ 'meta' ][ 'audit_params' ] ?? []
		);
	}

	public function test_accepted_reset_request_logs_requested_event() :void {
		$user = $this->makeUser( 34, 'reset-user' );
		$this->installServices( [ $user ], [ 'user_login' => 'reset-user' ] );
		$auditor = new Users();

		$auditor->capturePasswordResetRequestErrors( new \WP_Error(), $user );
		$message = $auditor->capturePasswordResetRequest( 'message body', 'reset-key', $user->user_login, $user );

		$this->assertSame( 'message body', $message );
		$this->assertSame(
			[
				[
					'event' => 'user_password_reset_requested',
					'meta'  => [
						'audit_params' => [
							'user_login' => 'reset-user',
						],
					],
				],
			],
			$this->events->firedEvents
		);
	}

	public function test_password_reset_logs_specific_reset_event_without_generic_password_update() :void {
		$user = $this->makeUser( 45, 'reset-complete-user' );
		$this->installServices( [ $user ] );
		$auditor = new Users();

		$auditor->captureUserPasswordResetStarted( $user, 'new-secret' );
		$auditor->captureUserPasswordSet( 'new-secret', $user->ID );
		$auditor->captureUserPasswordReset( $user, 'new-secret' );

		$this->assertSame(
			[ 'user_password_reset' ],
			\array_column( $this->events->firedEvents, 'event' )
		);
		$this->assertSame(
			[ 'user_login' => 'reset-complete-user' ],
			$this->events->firedEvents[ 0 ][ 'meta' ][ 'audit_params' ] ?? []
		);
	}

	public function test_ordinary_password_update_still_logs_generic_event() :void {
		$user = $this->makeUser( 56, 'ordinary-update-user' );
		$this->installServices( [ $user ] );

		( new Users() )->captureUserPasswordSet( 'new-secret', $user->ID );

		$this->assertSame(
			[
				[
					'event' => 'user_password_updated',
					'meta'  => [
						'audit_params' => [
							'user_login' => 'ordinary-update-user',
						],
					],
				],
			],
			$this->events->firedEvents
		);
	}

	private function installServices( array $users = [], array $postValues = [] ) :void {
		ServicesState::installItems( [
			'service_request' => new PasswordResetAuditRequestService( $postValues ),
			'service_wpusers' => new PasswordResetAuditUsersService( $users ),
		] );
	}

	private function makeUser( int $id, string $login ) :\WP_User {
		$user = new \WP_User();
		$user->ID = $id;
		$user->user_login = $login;
		$user->user_email = $login.'@example.test';
		return $user;
	}
}

class PasswordResetAuditEventsRecorder {

	public array $firedEvents = [];

	public function fireEvent( string $event, array $meta = [] ) :void {
		$this->firedEvents[] = [
			'event' => $event,
			'meta'  => $meta,
		];
	}
}

class PasswordResetAuditActivityLogStub {

	public array $updatedItems = [];

	public function updateItemOnSnapshot( object $auditor, object $item ) :void {
		$this->updatedItems[] = [
			'auditor' => $auditor,
			'item'    => $item,
		];
	}
}

class PasswordResetAuditRequestService extends Core\Request {

	private array $postValues;

	public function __construct( array $postValues = [] ) {
		$this->postValues = $postValues;
	}

	public function post( $key, $default = null ) {
		return $this->postValues[ $key ] ?? $default;
	}
}

class PasswordResetAuditUsersService extends Core\Users {

	/**
	 * @var \WP_User[]
	 */
	private array $usersByID = [];

	/**
	 * @param \WP_User[] $users
	 */
	public function __construct( array $users = [] ) {
		foreach ( $users as $user ) {
			$this->usersByID[ (int)$user->ID ] = $user;
		}
	}

	public function getUserById( $id ) {
		return $this->usersByID[ (int)$id ] ?? null;
	}

	public function isAppPasswordAuth() :bool {
		return false;
	}
}

}
