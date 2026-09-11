<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Events\LoginSuccessRecorder;

/** Isolates the request-local recorder without resetting production services. */
trait LoginSuccessFixture {

	private ?LoginSuccessRecorder $savedLoginRecorder = null;

	/** @var array<string,mixed> */
	private array $savedLoginHooks = [];

	private array $loginTimeline = [];

	protected function startLoginSuccessFixture( bool $execute = true ) :LoginSuccessRecorder {
		global $wp_filter;
		$con = $this->requireController();
		$this->savedLoginRecorder = $con->comps->login_success;
		foreach ( [ 'set_logged_in_cookie', 'clear_auth_cookie', 'wp_login', 'shield/event', $con->prefix( 'plugin_shutdown' ) ] as $hook ) {
			$this->savedLoginHooks[ $hook ] = isset( $wp_filter[ $hook ] ) ? clone $wp_filter[ $hook ] : null;
		}
		\remove_action( 'set_logged_in_cookie', [ $this->savedLoginRecorder, 'onSetLoggedInCookie' ], 5 );
		\remove_action( 'clear_auth_cookie', [ $this->savedLoginRecorder, 'onClearAuthCookie' ], 0 );
		\remove_action( 'wp_login', [ $this->savedLoginRecorder, 'onWpLogin' ], 20 );
		$recorder = new LoginSuccessRecorder();
		$con->comps->login_success = $recorder;
		if ( $execute ) {
			$recorder->execute();
		}
		$this->loginTimeline = [];
		\add_action( 'set_logged_in_cookie', function () {
			$this->loginTimeline[] = 'cookie';
		}, 6 );
		\add_action( 'shield/event', function ( $event ) {
			if ( \in_array( $event, [ 'login_success', '2fa_success' ], true ) ) {
				$this->loginTimeline[] = $event;
			}
		}, 1 );
		return $recorder;
	}

	protected function stopLoginSuccessFixture() :void {
		if ( $this->savedLoginRecorder === null ) {
			return;
		}
		global $wp_filter;
		$this->requireController()->comps->login_success = $this->savedLoginRecorder;
		foreach ( $this->savedLoginHooks as $name => $hook ) {
			if ( $hook === null ) {
				unset( $wp_filter[ $name ] );
			}
			else {
				$wp_filter[ $name ] = $hook;
			}
		}
		$this->savedLoginRecorder = null;
		$this->savedLoginHooks = [];
	}

	protected function assertLoginSuccessCount( int $count ) :void {
		$this->assertCount( $count, \array_filter( $this->loginTimeline, static fn( $event ) => $event === 'login_success' ) );
	}

	protected function assertMfaLoginCompleted() :void {
		$this->assertSame( [ 'cookie', '2fa_success', 'login_success' ], $this->loginTimeline );
	}
}
