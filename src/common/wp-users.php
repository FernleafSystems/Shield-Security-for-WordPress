<?php
if ( !class_exists( 'ICWP_WPSF_WpUsers', false ) ):

	class ICWP_WPSF_WpUsers {

		/**
		 * @var ICWP_WPSF_WpUsers
		 */
		protected static $oInstance = NULL;

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
		 * @return integer
		 */
		public function getCurrentUserLevel() {
			$oUser = $this->getCurrentWpUser();
			return ( is_object($oUser) && ($oUser instanceof WP_User) )? $oUser->get( 'user_level' ) : -1;
		}

		/**
		 * @return null|WP_User
		 */
		public function getCurrentWpUser() {
			if ( is_user_logged_in() ) {
				$oUser = wp_get_current_user();
				if ( is_object( $oUser ) && $oUser instanceof WP_User ) {
					return $oUser;
				}
			}
			return null;
		}

		/**
		 * @param string $sKey should be already prefixed
		 * @param int|null $nId - if omitted get for current user
		 * @return bool|string
		 */
		public function getUserMeta( $sKey, $nId = null ) {
			$nUserId = $nId;
			if ( empty( $nUserId ) ) {
				$oCurrentUser = $this->getCurrentWpUser();
				if ( is_null( $oCurrentUser ) ) {
					return false;
				}
				$nUserId = $oCurrentUser->ID;
			}

			$sCurrentMetaValue = get_user_meta( $nUserId, $sKey, true );
			// A guard whereby if we can't ever get a value for this meta, it means we can never set it.
			if ( empty( $sCurrentMetaValue ) ) {
				//the value has never been set, or it's been installed for the first time.
				$this->updateUserMeta( $sKey, 'temp', $nUserId );
				return '';
			}
			return $sCurrentMetaValue;
		}

		/**
		 * Updates the user meta data for the current (or supplied user ID)
		 *
		 * @param string $sKey
		 * @param mixed $mValue
		 * @param integer $nId		-user ID
		 * @return boolean
		 */
		public function updateUserMeta( $sKey, $mValue, $nId = null ) {
			$nUserId = $nId;
			if ( empty( $nUserId ) ) {
				$oCurrentUser = $this->getCurrentWpUser();
				if ( is_null( $oCurrentUser ) ) {
					return false;
				}
				$nUserId = $oCurrentUser->ID;
			}
			return update_user_meta( $nUserId, $sKey, $mValue );
		}
	}

endif;