<?php

class ICWP_WPSF_Processor_AdminAccessRestriction extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var string
	 */
	protected $sOptionRegexPattern;

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getMod();

		add_filter( $oFO->prefix( 'is_plugin_admin' ), array( $this, 'adjustUserAdminPermissions' ) );

		if ( $oFO->isWlEnabled() ) {
			$this->runWhiteLabel();
		}
	}

	/**
	 * @param bool $bHasPermission
	 * @return bool
	 */
	public function adjustUserAdminPermissions( $bHasPermission = true ) {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getMod();
		return $bHasPermission &&
			   ( $oFO->isRegisteredSecAdminUser() || $oFO->isSecAdminSessionValid()
				 || $oFO->checkAdminAccessKeySubmission() );
	}

	public function onWpInit() {
		parent::onWpInit();

		if ( !$this->getCon()->isPluginAdmin() ) {
			/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
			$oFO = $this->getMod();

			if ( !$oFO->isUpgrading() && !$this->loadWp()->isRequestUserLogin() ) {
				add_filter( 'pre_update_option', array( $this, 'blockOptionsSaves' ), 1, 3 );
			}

			if ( $oFO->isAdminAccessAdminUsersEnabled() ) {
				add_filter( 'editable_roles', array( $this, 'restrictEditableRoles' ), 100, 1 );
				add_filter( 'user_has_cap', array( $this, 'restrictAdminUserChanges' ), 100, 3 );
				add_action( 'delete_user', array( $this, 'restrictAdminUserDelete' ), 100, 1 );
				add_action( 'add_user_role', array( $this, 'restrictAddUserRole' ), 100, 2 );
				add_action( 'remove_user_role', array( $this, 'restrictRemoveUserRole' ), 100, 2 );
				add_action( 'set_user_role', array( $this, 'restrictSetUserRole' ), 100, 3 );
			}

			$aPluginRestrictions = $oFO->getAdminAccessArea_Plugins();
			if ( !empty( $aPluginRestrictions ) ) {
				add_filter( 'user_has_cap', array( $this, 'disablePluginManipulation' ), 0, 3 );
			}

			$aThemeRestrictions = $oFO->getAdminAccessArea_Themes();
			if ( !empty( $aThemeRestrictions ) ) {
				add_filter( 'user_has_cap', array( $this, 'disableThemeManipulation' ), 0, 3 );
			}

			$aPostRestrictions = $oFO->getAdminAccessArea_Posts();
			if ( !empty( $aPostRestrictions ) ) {
				add_filter( 'user_has_cap', array( $this, 'disablePostsManipulation' ), 0, 3 );
			}

			if ( !$this->getCon()->isThisPluginModuleRequest() ) {
				add_action( 'admin_footer', array( $this, 'printAdminAccessAjaxForm' ) );
			}
		}
	}

	/**
	 */
	protected function runWhiteLabel() {
		$this->getSubProcessorWhitelabel()
			 ->run();
	}

	/**
	 * @return ICWP_WPSF_Processor_AdminAccess_Whitelabel
	 */
	protected function getSubProcessorWhitelabel() {
		$oProc = $this->getSubPro( 'wl' );
		if ( is_null( $oProc ) ) {
			require_once( __DIR__.'/adminaccess_whitelabel.php' );
			$oProc = new ICWP_WPSF_Processor_AdminAccess_Whitelabel( $this->getMod() );
			$this->aSubPros[ 'wl' ] = $oProc;
		}
		return $oProc;
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		$aData = parent::tracking_DataCollect( $aData );
		$sSlug = $this->getMod()->getSlug();

		$aKeysToBoolean = array(
			'admin_access_restrict_plugins',
			'admin_access_restrict_themes',
			'admin_access_restrict_posts'
		);
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
		$oWpUsers = $this->loadWpUsers();

		if ( $oWpUsers->getCurrentWpUserId() !== $nUserId && strtolower( $sRole ) === 'administrator' ) {
			$oModUser = $oWpUsers->getUserById( $nUserId );
			remove_action( 'remove_user_role', array( $this, 'restrictRemoveUserRole' ), 100 );
			$oModUser->remove_role( 'administrator' );
			add_action( 'remove_user_role', array( $this, 'restrictRemoveUserRole' ), 100, 2 );
		}
	}

	/**
	 * @param int    $nUserId
	 * @param string $sRole
	 * @param array  $aOldRoles
	 */
	public function restrictSetUserRole( $nUserId, $sRole, $aOldRoles = array() ) {
		$oWpUsers = $this->loadWpUsers();

		$sRole = strtolower( $sRole );
		if ( !is_array( $aOldRoles ) ) {
			$aOldRoles = array();
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
				remove_action( 'add_user_role', array( $this, 'restrictAddUserRole' ), 100 );
				remove_action( 'remove_user_role', array( $this, 'restrictRemoveUserRole' ), 100 );
				$oModUser->remove_role( $sRole );
				foreach ( $aOldRoles as $sPreExistingRoles ) {
					$oModUser->add_role( $sPreExistingRoles );
				}
				add_action( 'add_user_role', array( $this, 'restrictAddUserRole' ), 100, 2 );
				add_action( 'remove_user_role', array( $this, 'restrictRemoveUserRole' ), 100, 2 );
			}
		}
	}

	/**
	 * @param int    $nUserId
	 * @param string $sRole
	 */
	public function restrictRemoveUserRole( $nUserId, $sRole ) {
		$oWpUsers = $this->loadWpUsers();

		if ( $oWpUsers->getCurrentWpUserId() !== $nUserId && strtolower( $sRole ) === 'administrator' ) {
			$oModUser = $oWpUsers->getUserById( $nUserId );
			remove_action( 'add_user_role', array( $this, 'restrictAddUserRole' ), 100 );
			$oModUser->add_role( 'administrator' );
			add_action( 'add_user_role', array( $this, 'restrictAddUserRole' ), 100, 2 );
		}
	}

	/**
	 * @param int $nId
	 */
	public function restrictAdminUserDelete( $nId ) {
		$oWpUsers = $this->loadWpUsers();
		$oUserToDelete = $oWpUsers->getUserById( $nId );
		if ( $oUserToDelete && $oWpUsers->isUserAdmin( $oUserToDelete ) ) {
			$this->loadWp()
				 ->wpDie( 'Sorry, deleting administrators is currently restricted to your Security Admin' );
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

		$aReleventCaps = array( 'edit_users', 'create_users' );

		// If we're registered with Admin Access we don't modify anything
		if ( in_array( $sUserCap, $aReleventCaps ) ) {
			$bBlockCapability = false;

			$oReq = $this->loadRequest();
			$oWpUsers = $this->loadWpUsers();

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
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 */
	public function addNotice_certain_options_restricted( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getMod();
		if ( $this->getCon()->isPluginAdmin() ) {
			return;
		}

		$sCurrentPage = $this->loadWp()->getCurrentPage();
		$sCurrentGetPage = $this->loadRequest()->query( 'page' );
		if ( !in_array( $sCurrentPage, $oFO->getOptionsPagesToRestrict() ) || !empty( $sCurrentGetPage ) ) {
			return;
		}

		$sName = $this->getCon()->getHumanName();
		$aRenderData = array(
			'notice_attributes' => $aNoticeAttributes,
			'strings'           => array(
				'title'          => sprintf( _wpsf__( '%s Security Restrictions Applied' ), $sName ),
				'notice_message' => _wpsf__( 'Altering certain options has been restricted by your WordPress security administrator.' )
									.' '._wpsf__( 'Repeated failed attempts to authenticate will probably lock you out of this site.' )
			),
			'hrefs'             => array(
				'setting_page' => sprintf(
					'<a href="%s" title="%s">%s</a>',
					$oFO->getUrl_AdminPage(),
					_wpsf__( 'Admin Access Login' ),
					sprintf( _wpsf__( 'Go here to manage settings and authenticate with the %s plugin.' ), $sName )
				)
			)
		);
		add_thickbox();
		$this->insertAdminNotice( $aRenderData );
	}

	/**
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 */
	public function addNotice_admin_users_restricted( $aNoticeAttributes ) {
		$oCon = $this->getCon();
		if ( $oCon->isPluginAdmin() ) {
			return;
		}

		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getMod();

		$sCurrentPage = $this->loadWp()->getCurrentPage();
		if ( !in_array( $sCurrentPage, $this->getUserPagesToRestrict() ) ) {
			return;
		}

		$sName = $oCon->getHumanName();
		$aRenderData = array(
			'notice_attributes' => $aNoticeAttributes,
			'strings'           => array(
				'title'          => sprintf( _wpsf__( '%s Security Restrictions Applied' ), $sName ),
				'notice_message' => _wpsf__( 'Editing existing administrators, promoting existing users to the administrator role, or deleting administrator users is currently restricted.' )
									.' '._wpsf__( 'Please authenticate with the Security Admin system before attempting any administrator user modifications.' ),
				'unlock_link'    => $this->getUnlockLinkHtml( _wpsf__( 'Unlock Now' ) ),
			),
			'hrefs'             => array(
				'setting_page' => sprintf(
					'<a href="%s" title="%s">%s</a>',
					$oFO->getUrl_AdminPage(),
					_wpsf__( 'Security Admin Login' ),
					sprintf( _wpsf__( 'Go here to manage settings and authenticate with the %s plugin.' ), $sName )
				)
			)
		);
		$this->insertAdminNotice( $aRenderData );
	}

	/**
	 * @return array
	 */
	protected function getUserPagesToRestrict() {
		return array(
			/* 'user-new.php', */
			'user-edit.php',
			'users.php',
		);
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
			$this->doStatIncrement( 'option.save.blocked' );
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
		$oReq = $this->loadRequest();

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

		$aEditCapabilities = array( 'activate_plugins', 'delete_plugins', 'install_plugins', 'update_plugins' );

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
		$aEditCapabilities = array(
			'switch_themes',
			'edit_theme_options',
			'install_themes',
			'update_themes',
			'delete_themes'
		);

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
		$aEditCapabilities = array(
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
		);
		if ( in_array( $sRequestedCapability, $aEditCapabilities ) ) {
			$sRequestedCapabilityTrimmed = str_replace( array(
				'_posts',
				'_pages',
				'_post',
				'_page'
			), '', $sRequestedCapability ); //Order of items in this array is important!
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

		$aRenderData = array(
			'flags'       => array(
				'restrict_options' => $oFO->getAdminAccessArea_Options()
			),
			'strings'     => array(
				'editing_restricted' => _wpsf__( 'Editing this option is currently restricted.' ),
				'unlock_link'        => $this->getUnlockLinkHtml(),
			),
			'js_snippets' => array(
				'options_to_restrict' => "'".implode( "','", $oFO->getOptionsToRestrict() )."'",
			),
			'ajax'        => array(
				'sec_admin_login_box' => $oFO->getAjaxActionData( 'sec_admin_login_box', true )
			)
		);
		add_thickbox();
		echo $oFO->renderTemplate( 'snippets/admin_access_login_box.php', $aRenderData );
	}

	/**
	 * @param string $sLinkText
	 * @return string
	 */
	protected function getUnlockLinkHtml( $sLinkText = '' ) {
		if ( empty( $sLinkText ) ) {
			$sLinkText = _wpsf__( 'Unlock' );
		}
		return sprintf(
			'<a href="%1$s" title="%2$s" class="thickbox">%3$s</a>',
			'#TB_inline?width=400&height=180&inlineId=WpsfAdminAccessLogin',
			_wpsf__( 'Security Admin Login' ),
			$sLinkText
		);
	}

	/**
	 * @deprecated
	 * @return bool
	 */
	protected function isSecurityAdmin() {
		return $this->getCon()->isPluginAdmin();
	}
}