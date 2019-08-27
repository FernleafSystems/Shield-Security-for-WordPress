<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_AdminAccessRestriction extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var string
	 */
	protected $sOptionRegexPattern;

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oMod */
		$oMod = $this->getMod();

		add_filter( $oMod->prefix( 'is_plugin_admin' ), [ $this, 'adjustUserAdminPermissions' ] );

		if ( $oMod->isWlEnabled() ) {
			$this->getSubProWhitelabel()->run();
		}
	}

	/**
	 * @param bool $bHasPermission
	 * @return bool
	 */
	public function adjustUserAdminPermissions( $bHasPermission = true ) {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getMod();
		return $bHasPermission && ( $oFO->isRegisteredSecAdminUser() || $oFO->isSecAdminSessionValid()
									|| $oFO->testSecAccessKeyRequest() );
	}

	public function onWpInit() {
		parent::onWpInit();

		if ( !$this->getCon()->isPluginAdmin() ) {
			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oMod */
			$oMod = $this->getMod();

			if ( !$oMod->isUpgrading() && !Services::WpGeneral()->isLoginRequest() ) {
				add_filter( 'pre_update_option', [ $this, 'blockOptionsSaves' ], 1, 3 );
			}

			if ( $oMod->isAdminAccessAdminUsersEnabled() ) {
				add_filter( 'editable_roles', [ $this, 'restrictEditableRoles' ], 100, 1 );
				add_filter( 'user_has_cap', [ $this, 'restrictAdminUserChanges' ], 100, 3 );
				add_action( 'delete_user', [ $this, 'restrictAdminUserDelete' ], 100, 1 );
				add_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100, 2 );
				add_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100, 2 );
				add_action( 'set_user_role', [ $this, 'restrictSetUserRole' ], 100, 3 );
			}

			$aPluginRestrictions = $oMod->getAdminAccessArea_Plugins();
			if ( !empty( $aPluginRestrictions ) ) {
				add_filter( 'user_has_cap', [ $this, 'disablePluginManipulation' ], 0, 3 );
			}

			$aThemeRestrictions = $oMod->getAdminAccessArea_Themes();
			if ( !empty( $aThemeRestrictions ) ) {
				add_filter( 'user_has_cap', [ $this, 'disableThemeManipulation' ], 0, 3 );
			}

			$aPostRestrictions = $oMod->getAdminAccessArea_Posts();
			if ( !empty( $aPostRestrictions ) ) {
				add_filter( 'user_has_cap', [ $this, 'disablePostsManipulation' ], 0, 3 );
			}

			if ( !$this->getCon()->isThisPluginModuleRequest() ) {
				add_action( 'admin_footer', [ $this, 'printAdminAccessAjaxForm' ] );
			}
		}
	}

	/**
	 * @return ICWP_WPSF_Processor_AdminAccess_Whitelabel|mixed
	 */
	protected function getSubProWhitelabel() {
		return $this->getSubPro( 'wl' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() {
		return [
			'wl' => 'ICWP_WPSF_Processor_AdminAccess_Whitelabel',
		];
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		$aData = parent::tracking_DataCollect( $aData );
		$sSlug = $this->getMod()->getSlug();

		$aKeysToBoolean = [
			'admin_access_restrict_plugins',
			'admin_access_restrict_themes',
			'admin_access_restrict_posts'
		];
		foreach ( $aKeysToBoolean as $sKeyToBoolean ) {
			$aData[ $sSlug ][ 'options' ][ $sKeyToBoolean ]
				= empty( $aData[ $sSlug ][ 'options' ][ $sKeyToBoolean ] ) ? 0 : 1;
		}
		return $aData;
	}

	/**
	 * @param int    $nUserId
	 * @param string $sRole
	 */
	public function restrictAddUserRole( $nUserId, $sRole ) {
		$oWpUsers = Services::WpUsers();

		if ( $oWpUsers->getCurrentWpUserId() !== $nUserId && strtolower( $sRole ) === 'administrator' ) {
			$oModUser = $oWpUsers->getUserById( $nUserId );
			remove_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100 );
			$oModUser->remove_role( 'administrator' );
			add_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100, 2 );
		}
	}

	/**
	 * @param int    $nUserId
	 * @param string $sRole
	 * @param array  $aOldRoles
	 */
	public function restrictSetUserRole( $nUserId, $sRole, $aOldRoles = [] ) {
		$oWpUsers = Services::WpUsers();

		$sRole = strtolower( $sRole );
		if ( !is_array( $aOldRoles ) ) {
			$aOldRoles = [];
		}

		if ( $oWpUsers->getCurrentWpUserId() !== $nUserId ) {
			$bNewRoleIsAdmin = $sRole == 'administrator';

			// 1. Setting administrator role where it doesn't previously exist
			if ( $bNewRoleIsAdmin && !in_array( 'administrator', $aOldRoles ) ) {
				$bRevert = true;
			}
			// 2. Setting non-administrator role when previous roles included administrator
			else if ( !$bNewRoleIsAdmin && in_array( 'administrator', $aOldRoles ) ) {
				$bRevert = true;
			}
			else {
				$bRevert = false;
			}

			if ( $bRevert ) {
				$oModUser = $oWpUsers->getUserById( $nUserId );
				remove_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100 );
				remove_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100 );
				$oModUser->remove_role( $sRole );
				foreach ( $aOldRoles as $sPreExistingRoles ) {
					$oModUser->add_role( $sPreExistingRoles );
				}
				add_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100, 2 );
				add_action( 'remove_user_role', [ $this, 'restrictRemoveUserRole' ], 100, 2 );
			}
		}
	}

	/**
	 * @param int    $nUserId
	 * @param string $sRole
	 */
	public function restrictRemoveUserRole( $nUserId, $sRole ) {
		$oWpUsers = Services::WpUsers();

		if ( $oWpUsers->getCurrentWpUserId() !== $nUserId && strtolower( $sRole ) === 'administrator' ) {
			$oModUser = $oWpUsers->getUserById( $nUserId );
			remove_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100 );
			$oModUser->add_role( 'administrator' );
			add_action( 'add_user_role', [ $this, 'restrictAddUserRole' ], 100, 2 );
		}
	}

	/**
	 * @param int $nId
	 */
	public function restrictAdminUserDelete( $nId ) {
		$oWpUsers = Services::WpUsers();
		$oUserToDelete = $oWpUsers->getUserById( $nId );
		if ( $oUserToDelete && $oWpUsers->isUserAdmin( $oUserToDelete ) ) {
			Services::WpGeneral()
					->wpDie( __( 'Sorry, deleting administrators is currently restricted to your Security Admin', 'wp-simple-firewall' ) );
		}
	}

	/**
	 * @param array[] $aAllRoles
	 * @return array[]
	 */
	public function restrictEditableRoles( $aAllRoles ) {
		if ( isset( $aAllRoles[ 'administrator' ] ) ) {
			unset( $aAllRoles[ 'administrator' ] );
		}
		return $aAllRoles;
	}

	/**
	 * This hooked function captures the attempts to modify the user role using the standard
	 * WordPress profile edit pages. It doesn't sufficiently capture the AJAX request to
	 * modify user roles. (see user role hooks)
	 * @param array $aAllCaps
	 * @param       $cap
	 * @param array $aArgs
	 * @return array
	 */
	public function restrictAdminUserChanges( $aAllCaps, $cap, $aArgs ) {
		/** @var string $sUserCap */
		$sUserCap = $aArgs[ 0 ];

		$aReleventCaps = [ 'edit_users', 'create_users' ];

		// If we're registered with Admin Access we don't modify anything
		if ( in_array( $sUserCap, $aReleventCaps ) ) {
			$bBlockCapability = false;

			$oReq = Services::Request();
			$oWpUsers = Services::WpUsers();

			// Find the WP_User for the POST
			$oPostUser = false;
			$sPostUserlogin = $oReq->post( 'user_login' );
			if ( empty( $sPostUserlogin ) ) {
				$nPostUserId = $oReq->post( 'user_id' );
				if ( !empty( $nPostUserId ) ) {
					$oPostUser = $oWpUsers->getUserById( $nPostUserId );
				}
			}
			else {
				$oPostUser = $oWpUsers->getUserByUsername( $sPostUserlogin );
			}

			$sRequestRole = strtolower( $oReq->post( 'role', '' ) );

			if ( $oPostUser instanceof WP_User ) {
				// editing an existing user other than yourself?
				if ( $oPostUser->user_login != $oWpUsers->getCurrentWpUsername() ) {

					if ( $oWpUsers->isUserAdmin( $oPostUser ) || ( $sRequestRole == 'administrator' ) ) {
						$bBlockCapability = true;
					}
				}
			}
			else {//creating a new admin user?
				if ( $sRequestRole == 'administrator' ) {
					$bBlockCapability = true;
				}
			}

			if ( $bBlockCapability ) {
				$aAllCaps[ $sUserCap ] = false;
			}
		}

		return $aAllCaps;
	}

	/**
	 * @return array
	 */
	protected function getUserPagesToRestrict() {
		return [
			/* 'user-new.php', */
			'user-edit.php',
			'users.php',
		];
	}

	/**
	 * Need to always re-test isPluginAdmin() because there's a dynamic filter in there to
	 * permit saving by the plugin itself.
	 *
	 * Right before a plugin option is due to update it will check that we have permissions to do so
	 * and if not, will * revert the option to save to the previous one.
	 * @param mixed  $mNewOptionValue
	 * @param string $sOptionKey
	 * @param mixed  $mOldValue
	 * @return mixed
	 */
	public function blockOptionsSaves( $mNewOptionValue, $sOptionKey, $mOldValue ) {

		if ( !$this->getCon()->isPluginAdmin()
			 && ( $this->isOptionForThisPlugin( $sOptionKey ) || $this->isOptionRestricted( $sOptionKey ) ) ) {
			$mNewOptionValue = $mOldValue;
		}

		return $mNewOptionValue;
	}

	/**
	 * @param string $sOptionKey
	 * @return int
	 */
	protected function isOptionForThisPlugin( $sOptionKey ) {
		return preg_match( $this->getOptionRegexPattern(), $sOptionKey );
	}

	/**
	 * @param string $sOptionKey
	 * @return int
	 */
	protected function isOptionRestricted( $sOptionKey ) {
		$bRestricted = false;
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getMod();
		if ( $oFO->getAdminAccessArea_Options() ) {
			$bRestricted = in_array(
				$sOptionKey,
				$oFO->getOptionsToRestrict()
			);
		}
		return $bRestricted;
	}

	/**
	 * @param array $aAllCaps
	 * @param       $cap
	 * @param array $aArgs
	 * @return array
	 */
	public function disablePluginManipulation( $aAllCaps, $cap, $aArgs ) {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getMod();
		$oReq = Services::Request();

		/** @var string $sRequestedCapability */
		$sRequestedCapability = $aArgs[ 0 ];

		// special case for plugin info thickbox for changelog
		$bIsChangelog = defined( 'IFRAME_REQUEST' )
						&& ( $sRequestedCapability === 'install_plugins' )
						&& ( $oReq->query( 'section' ) == 'changelog' )
						&& $oReq->query( 'plugin' );
		if ( $bIsChangelog ) {
			return $aAllCaps;
		}

		$aEditCapabilities = [ 'activate_plugins', 'delete_plugins', 'install_plugins', 'update_plugins' ];

		if ( in_array( $sRequestedCapability, $aEditCapabilities ) ) {
			$aAreaRestrictions = $oFO->getAdminAccessArea_Plugins();
			if ( in_array( $sRequestedCapability, $aAreaRestrictions ) ) {
				$aAllCaps[ $sRequestedCapability ] = false;
			}
		}

		return $aAllCaps;
	}

	/**
	 * @param array $aAllCaps
	 * @param       $cap
	 * @param array $aArgs
	 * @return array
	 */
	public function disableThemeManipulation( $aAllCaps, $cap, $aArgs ) {
		// If we're registered with Admin Access we don't modify anything
		if ( $this->getCon()->isPluginAdmin() ) {
			return $aAllCaps;
		}

		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getMod();

		/** @var string $sRequestedCapability */
		$sRequestedCapability = $aArgs[ 0 ];
		$aEditCapabilities = [
			'switch_themes',
			'edit_theme_options',
			'install_themes',
			'update_themes',
			'delete_themes'
		];

		if ( in_array( $sRequestedCapability, $aEditCapabilities ) ) {
			$aAreaRestrictions = $oFO->getAdminAccessArea_Themes();
			if ( in_array( $sRequestedCapability, $aAreaRestrictions ) ) {
				$aAllCaps[ $sRequestedCapability ] = false;
			}
		}

		return $aAllCaps;
	}

	/**
	 * @param array $aAllCaps
	 * @param       $cap
	 * @param array $aArgs
	 * @return array
	 */
	public function disablePostsManipulation( $aAllCaps, $cap, $aArgs ) {
		if ( $this->getCon()->isPluginAdmin() ) {
			return $aAllCaps;
		}

		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getMod();

		/** @var string $sRequestedCapability */
		$sRequestedCapability = $aArgs[ 0 ];
		$aEditCapabilities = [
			'edit_post',
			'publish_post',
			'delete_post',
			'edit_posts',
			'publish_posts',
			'delete_posts',
			'edit_page',
			'publish_page',
			'delete_page',
			'edit_pages',
			'publish_pages',
			'delete_pages'
		];
		if ( in_array( $sRequestedCapability, $aEditCapabilities ) ) {
			$sRequestedCapabilityTrimmed = str_replace( [
				'_posts',
				'_pages',
				'_post',
				'_page'
			], '', $sRequestedCapability ); //Order of items in this array is important!
			$aAreaRestrictions = $oFO->getAdminAccessArea_Posts();
			if ( in_array( $sRequestedCapabilityTrimmed, $aAreaRestrictions ) ) {
				$aAllCaps[ $sRequestedCapability ] = false;
			}
		}
		return $aAllCaps;
	}

	/**
	 * @return string
	 */
	protected function getOptionRegexPattern() {
		if ( !isset( $this->sOptionRegexPattern ) ) {
			$this->sOptionRegexPattern = sprintf( '/^%s.*_options$/',
				$this->getMod()->getOptionStoragePrefix()
			);
		}
		return $this->sOptionRegexPattern;
	}

	public function printAdminAccessAjaxForm() {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getMod();

		$aRenderData = [
			'flags'       => [
				'restrict_options' => $oFO->getAdminAccessArea_Options()
			],
			'strings'     => [
				'editing_restricted' => __( 'Editing this option is currently restricted.', 'wp-simple-firewall' ),
				'unlock_link'        => $this->getUnlockLinkHtml(),
			],
			'js_snippets' => [
				'options_to_restrict' => "'".implode( "','", $oFO->getOptionsToRestrict() )."'",
			],
			'ajax'        => [
				'sec_admin_login_box' => $oFO->getAjaxActionData( 'sec_admin_login_box', true )
			]
		];
		add_thickbox();
		echo $oFO->renderTemplate( 'snippets/admin_access_login_box.php', $aRenderData );
	}

	/**
	 * @param string $sLinkText
	 * @return string
	 */
	protected function getUnlockLinkHtml( $sLinkText = '' ) {
		if ( empty( $sLinkText ) ) {
			$sLinkText = __( 'Unlock', 'wp-simple-firewall' );
		}
		return sprintf(
			'<a href="%1$s" title="%2$s" class="thickbox">%3$s</a>',
			'#TB_inline?width=400&height=180&inlineId=WpsfAdminAccessLogin',
			__( 'Security Admin Login', 'wp-simple-firewall' ),
			$sLinkText
		);
	}

	/**
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 * @deprecated
	 */
	public function addNotice_certain_options_restricted( $aNoticeAttributes ) {
		return;
	}

	/**
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 * @deprecated
	 */
	public function addNotice_admin_users_restricted( $aNoticeAttributes ) {
		return;
	}
}