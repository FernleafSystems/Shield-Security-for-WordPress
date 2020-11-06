<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer;

use FernleafSystems\Wordpress\Services\Services;

trait WpLoginCapture {

	/**
	 * @var bool
	 */
	private $isLoginCaptured = false;

	abstract protected function captureLogin( \WP_User $user );

	protected function getLoginPassword() :string {
		$pass = '';
		foreach ( [ 'pwd', 'pass1', 'password', 'edd_user_pass' ] as $key ) {
			$maybe = Services::Request()->request( $key );
			if ( !empty( $maybe ) ) {
				$pass = $maybe;
				break;
			}
		}
		return $pass;
	}

	protected function isLoginCaptured() :bool {
		return $this->isLoginCaptured;
	}

	protected function setLoginCaptured( bool $captured = true ) :self {
		$this->isLoginCaptured = $captured;
		return $this;
	}

	protected function setupLoginCaptureHooks() {
		add_action( 'wp_login', [ $this, 'onWpLogin' ], 10, 2 );
		if ( !Services::WpUsers()->isProfilePage() ) { // Ignore firing during profile update.
			add_action( 'set_logged_in_cookie', [ $this, 'onWpSetLoggedInCookie' ], 5, 4 );
		}
	}

	/**
	 * @param string $cookie
	 * @param int    $expire
	 * @param int    $expiration
	 * @param int    $userID
	 */
	public function onWpSetLoggedInCookie( $cookie, $expire, $expiration, $userID ) {
		$user = Services::WpUsers()->getUserById( $userID );
		if ( !$this->isLoginCaptured() && $user instanceof \WP_User ) {
			$this->setLoginCaptured();
			$this->captureLogin( $user );
		}
	}

	/**
	 * @param string   $username
	 * @param \WP_User $user
	 */
	public function onWpLogin( $username, $user ) {
		if ( !$this->isLoginCaptured() && $user instanceof \WP_User ) {
			$this->setLoginCaptured();
			$this->captureLogin( $user );
		}
	}
}