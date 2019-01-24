<?php

class ICWP_WPSF_WpUsers extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_UserMeta[]
	 */
	private $aMetas;

	/**
	 * @var ICWP_WPSF_WpUsers
	 */
	protected static $oInstance = null;

	private function __construct() {
	}

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
	 * @param array $aArgs
	 * @return WP_User[]
	 */
	public function getAllUsers( $aArgs = array() ) {
		$aArgs = wp_parse_args(
			$aArgs,
			array(
				'blog_id' => 0,
				//					'fields' => array(
				//						'ID',
				//						'user_login',
				//						'user_email',
				//						'user_pass',
				//					)
			)
		);
		return function_exists( 'get_users' ) ? get_users( $aArgs ) : array();
	}

	/**
	 * @return integer
	 */
	public function getCurrentUserLevel() {
		$oUser = $this->getCurrentWpUser();
		return ( is_object( $oUser ) && ( $oUser instanceof WP_User ) ) ? $oUser->get( 'user_level' ) : -1;
	}

	/**
	 * @return array
	 */
	public function getLevelToRoleMap() {
		return array(
			0 => 'subscriber',
			1 => 'contributor',
			2 => 'author',
			3 => 'editor',
			8 => 'administrator'
		);
	}

	/**
	 * @param bool $bSlugsOnly
	 * @return string[]|array[]
	 */
	public function getAvailableUserRoles( $bSlugsOnly = true ) {
		require_once( ABSPATH.'wp-admin/includes/user.php' );
		return $bSlugsOnly ? array_keys( get_editable_roles() ) : get_editable_roles();
	}

	/**
	 * @return bool
	 */
	public function canSaveMeta() {
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
		return $this->isUserLoggedIn() ? wp_get_current_user() : null;
	}

	/**
	 * @return int - 0 if not logged in or can't get the current User
	 */
	public function getCurrentWpUserId() {
		$oUser = $this->getCurrentWpUser();
		return is_null( $oUser ) ? 0 : $oUser->ID;
	}

	/**
	 * @return null|string
	 */
	public function getCurrentWpUsername() {
		$oUser = $this->getCurrentWpUser();
		return is_null( $oUser ) ? null : $oUser->user_login;
	}

	/**
	 * @param WP_User $oUser
	 * @return string
	 */
	public function getAdminUrl_ProfileEdit( $oUser = null ) {
		if ( $oUser instanceof WP_User ) {
			$sPath = 'user-edit.php?user_id='.$oUser->ID;
		}
		else {
			$sPath = 'profile.php';
		}
		return $this->loadWp()->getAdminUrl( $sPath );
	}

	/**
	 * @param string $sEmail
	 * @return WP_User|null
	 */
	public function getUserByEmail( $sEmail ) {
		return $this->getUserBy( 'email', $sEmail );
	}

	/**
	 * @param int $nId
	 * @return WP_User|null
	 */
	public function getUserById( $nId ) {
		return $this->getUserBy( 'id', $nId );
	}

	/**
	 * @param $sUsername
	 * @return null|WP_User
	 */
	public function getUserByUsername( $sUsername ) {
		return $this->getUserBy( 'login', $sUsername );
	}

	/**
	 * @param string $sKey
	 * @param mixed  $mValue
	 * @return null|WP_User
	 */
	public function getUserBy( $sKey, $mValue ) {
		$oU = function_exists( 'get_user_by' ) ? get_user_by( $sKey, $mValue ) : null;
		return empty( $oU ) ? null : $oU;
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
	 * @see wp-login.php
	 * @param WP_User $oUser
	 * @return string|null
	 */
	public function getPasswordResetUrl( $oUser ) {
		$sUrl = null;

		$sResetKey = get_password_reset_key( $oUser );
		if ( !is_wp_error( $sResetKey ) ) {
			$sUrl = add_query_arg(
				array(
					'action' => 'rp',
					'key'    => $sResetKey,
					'login'  => $oUser->user_login,
				),
				wp_login_url()
			);
		}

		return $sUrl;
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
	 * @param string $sPrefix
	 * @param int    $nUserId
	 * @return ICWP_UserMeta
	 */
	public function metaVoForUser( $sPrefix, $nUserId = null ) {
		if ( is_null( $nUserId ) ) {
			$nUserId = $this->getCurrentWpUserId();
		}

		$aMetas = $this->getMetas();
		if ( !isset( $aMetas[ $nUserId ] ) ) {
			$aMetas[ $nUserId ] = new ICWP_UserMeta( $sPrefix, $nUserId );
			$this->aMetas = $aMetas;
		}
		return $this->aMetas[ $nUserId ];
	}

	/**
	 * @return ICWP_UserMeta[]
	 */
	protected function getMetas() {
		if ( empty( $this->aMetas ) ) {
			$this->aMetas = array();
		}
		return $this->aMetas;
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
		$bSuccess = $oUser instanceof WP_User;
		if ( $bSuccess ) {
			wp_clear_auth_cookie();
			wp_set_current_user( $oUser->ID, $oUser->user_login );
			wp_set_auth_cookie( $oUser->ID, true );
			do_action( 'wp_login', $oUser->user_login, $oUser );
		}
		return $bSuccess;
	}
}