<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer;

use FernleafSystems\Wordpress\Services\Services;

trait WpLoginCapture {

	/**
	 * @var bool
	 */
	private $isLoginCaptured = false;

	/**
	 * @var bool
	 */
	private $allowMultipleCapture = false;

	/**
	 * @var bool
	 */
	private $isCaptureApplicationLogin = false;

	/**
	 * @var string
	 */
	private $loggedInCookie = '';

	/**
	 * @var int
	 */
	private $capturedUserID = null;

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

	protected function getLoggedInCookie() :string {
		$cookie = empty( $this->loggedInCookie ) ?
			Services::Request()->cookie( LOGGED_IN_COOKIE ) : $this->loggedInCookie;
		return \is_string( $cookie ) ? $cookie : '';
	}

	protected function getCapturedUserID() :int {
		return \is_int( $this->capturedUserID ) ? $this->capturedUserID : 0;
	}

	protected function isCaptureApplicationLogin() :bool {
		return $this->isCaptureApplicationLogin;
	}

	protected function isLoginCaptured() :bool {
		return $this->isLoginCaptured;
	}

	/**
	 * By default, will only capture logins if it's not an API request, or it's set to capture api requests also.
	 */
	protected function isLoginToBeCaptured() :bool {
		return !Services::WpGeneral()->isApplicationPasswordApiRequest() || $this->isCaptureApplicationLogin();
	}

	protected function setLoginCaptured( bool $captured = true ) :self {
		$this->isLoginCaptured = $captured;
		return $this;
	}

	protected function setLoggedInCookie( string $cookieValue ) :self {
		$this->loggedInCookie = $cookieValue;
		return $this;
	}

	protected function setToCaptureApplicationLogin( bool $capture = true ) :self {
		$this->isCaptureApplicationLogin = $capture;
		return $this;
	}

	protected function setAllowMultipleCapture( bool $multiple = true ) :self {
		$this->allowMultipleCapture = $multiple;
		return $this;
	}

	protected function setupLoginCaptureHooks() {
		add_action( 'wp_login', [ $this, 'onWpLogin' ], $this->getHookPriority(), 2 );
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
		if ( \is_string( $cookie ) ) {
			$this->setLoggedInCookie( $cookie );
		}
		if ( $user instanceof \WP_User
			 && $this->isLoginToBeCaptured()
			 && ( $this->allowMultipleCapture || !$this->isLoginCaptured() ) ) {
			$this->setLoginCaptured();
			$this->capturedUserID = $user->ID;
			$this->captureLogin( $user );
		}
	}

	/**
	 * @param string   $username
	 * @param \WP_User $user
	 */
	public function onWpLogin( $username, $user ) {
		if ( $user instanceof \WP_User
			 && $this->isLoginToBeCaptured()
			 && ( $this->allowMultipleCapture || !$this->isLoginCaptured() ) ) {
			$this->setLoginCaptured();
			$this->capturedUserID = $user->ID;
			$this->captureLogin( $user );
		}
	}

	protected function getHookPriority() :int {
		return 10;
	}
}