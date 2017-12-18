<?php
if ( class_exists( 'ICWP_WPSF_WpUsers', false ) ) {
	return;
}

class ICWP_WPSF_WpUsers extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_WpUsers
	 */
	protected static $oInstance = null;

	private function __construct() {}

	/**
	 * @return ICWP_WPSF_WpUsers
	 */
	public static function GetInstance() {
		if ( is_null( self::$oInstance ) ) {
			self::$oInstance = new self();
		}
		return self::$oInstance;
	}

	/**
	 * @param string  $sKey
	 * @param integer $nUserId -user ID
	 * @return boolean
	 */
	public function deleteUserMeta( $sKey, $nUserId = null ) {
		if ( empty( $nUserId ) ) {
			$nUserId = $this->getCurrentWpUserId();
		}
		else if ( $nUserId instanceof WP_User ) {
			$nUserId = $nUserId->ID;
		}

		$bSuccess = false;
		if ( $nUserId > 0 ) {
			$bSuccess = delete_user_meta( $nUserId, $sKey );
		}
		return $bSuccess;
	}

	/**
	 * @param array $aLoginUrlParams
	 */
	public function forceUserRelogin( $aLoginUrlParams = array() ) {
		$this->logoutUser();
		$this->loadWp()->redirectToLogin( $aLoginUrlParams );
	}

	/**
	 * @return integer
	 */
	public function getCurrentUserLevel() {
		$oUser = $this->getCurrentWpUser();
		return ( is_object( $oUser ) && ( $oUser instanceof WP_User ) ) ? $oUser->get( 'user_level' ) : -1;
	}

	/**
	 * @return bool
	 */
	public function getCanAddUpdateCurrentUserMeta() {
		$bCanMeta = false;
		try {
			if ( $this->isUserLoggedIn() ) {
				$sKey = 'icwp-flag-can-store-user-meta';
				$sMeta = $this->getUserMeta( $sKey );
				if ( $sMeta == 'icwp' ) {
					$bCanMeta = true;
				}
				else {
					$bCanMeta = $this->updateUserMeta( $sKey, 'icwp' );
				}
			}
		}
		catch ( Exception $oE ) {
		}
		return $bCanMeta;
	}

	/**
	 * @return null|WP_User
	 */
	public function getCurrentWpUser() {
		$oUser = null;
		if ( $this->isUserLoggedIn() ) {
			$oUser = wp_get_current_user();
		}
		return $oUser;
	}

	/**
	 * @return int - 0 if not logged in or can't get the current User
	 */
	public function getCurrentWpUserId() {
		$oUser = $this->getCurrentWpUser();
		$nId = is_null( $oUser ) ? 0 : $oUser->ID;
		return $nId;
	}

	/**
	 * @param $sUsername
	 * @return false|WP_User
	 */
	public function getUserByUsername( $sUsername ) {
		if ( empty( $sUsername ) ) {
			return false;
		}

		if ( $this->loadWp()->getWordpressIsAtLeastVersion( '2.8.0' ) ) {
			$oUser = get_user_by( 'login', $sUsername );
		}
		else {
			$oUser = get_userdatabylogin( $sUsername );
		}

		return $oUser;
	}

	/**
	 * @param int $nId
	 * @return WP_User|null
	 */
	public function getUserById( $nId ) {
		return function_exists( 'get_user_by' ) ? get_user_by( 'id', $nId ) : null;
	}

	/**
	 * @param string   $sKey    should be already prefixed
	 * @param int|null $nUserId - if omitted get for current user
	 * @return false|string
	 */
	public function getUserMeta( $sKey, $nUserId = null ) {
		if ( empty( $nUserId ) ) {
			$nUserId = $this->getCurrentWpUserId();
		}
		else if ( $nUserId instanceof WP_User ) {
			$nUserId = $nUserId->ID;
		}

		$mResult = false;
		if ( $nUserId > 0 ) {
			$mResult = get_user_meta( $nUserId, $sKey, true );
		}
		return $mResult;
	}

	/**
	 * @param WP_User|null $oUser
	 * @return bool
	 */
	public function isUserAdmin( $oUser = null ) {
		if ( empty( $oUser ) ) {
			$bIsAdmin = $this->isUserLoggedIn() && current_user_can( 'manage_options' );
		}
		else {
			$bIsAdmin = user_can( $oUser, 'manage_options' );
		}
		return $bIsAdmin;
	}

	/**
	 * @return bool
	 */
	public function isUserLoggedIn() {
		return function_exists( 'is_user_logged_in' ) && is_user_logged_in();
	}

	/**
	 * Fires the WordPress logout functions.  If $bQuiet is true, it'll manually
	 * call the WordPress logout code, so as not to fire any other logout actions
	 * We might want to be "quiet" so as not to fire out own action hooks.
	 * @param bool $bQuiet
	 */
	public function logoutUser( $bQuiet = false ) {
		if ( $bQuiet ) {
			wp_destroy_current_session();
			wp_clear_auth_cookie();
		}
		else {
			wp_logout();
		}
	}

	/**
	 * Updates the user meta data for the current (or supplied user ID)
	 * @param string      $sKey
	 * @param mixed       $mValue
	 * @param WP_User|int $nUserId -user ID
	 * @return boolean
	 */
	public function updateUserMeta( $sKey, $mValue, $nUserId = null ) {
		if ( empty( $nUserId ) ) {
			$nUserId = $this->getCurrentWpUserId();
		}
		else if ( $nUserId instanceof WP_User ) {
			$nUserId = $nUserId->ID;
		}

		$bSuccess = false;
		if ( $nUserId > 0 ) {
			$bSuccess = update_user_meta( $nUserId, $sKey, $mValue );
		}
		return $bSuccess;
	}

	/**
	 * @param string $sUsername
	 * @return bool
	 */
	public function setUserLoggedIn( $sUsername ) {

		$oUser = $this->getUserByUsername( $sUsername );
		if ( !is_a( $oUser, 'WP_User' ) ) {
			return false;
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $oUser->ID, $oUser->get( 'user_login' ) );
		wp_set_auth_cookie( $oUser->ID, true );
		do_action( 'wp_login', $oUser->get( 'user_login' ), $oUser );

		return true;
	}
}