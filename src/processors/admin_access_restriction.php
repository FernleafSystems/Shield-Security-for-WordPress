<?php

if ( class_exists( 'ICWP_WPSF_Processor_AdminAccessRestriction', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_AdminAccessRestriction extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * @var string
	 */
	protected $sOptionRegexPattern;

	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getFeature();
		$oWp = $this->loadWp();

		add_filter( $oFO->prefix( 'has_permission_to_manage' ), array( $oFO, 'doCheckHasPermissionToSubmit' ) );
		add_filter( $oFO->prefix( 'has_permission_to_view' ), array( $oFO, 'doCheckHasPermissionToSubmit' ) );

		if ( !$oFO->getIsUpgrading() && !$oWp->isRequestUserLogin() ) {
			add_filter( 'pre_update_option', array( $this, 'blockOptionsSaves' ), 1, 3 );
		}

		if ( $oFO->getOptIs( 'admin_access_restrict_admin_users', 'Y' ) ) {
			add_filter( 'user_has_cap', array( $this, 'restrictAdminUserChanges' ), 0, 3 );
			add_action( 'delete_user', array( $this, 'restrictAdminUserDelete' ), 0, 1 );
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

		add_action( 'admin_footer', array( $this, 'printAdminAccessAjaxForm' ) );

		if ( $oFO->isWlEnabled() ) {
			$this->runWhiteLabel();
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
		$oProc = $this->getSubProcessor( 'checksum' );
		if ( is_null( $oProc ) ) {
			require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'adminaccess_whitelabel.php' );
			$oProc = new ICWP_WPSF_Processor_AdminAccess_Whitelabel( $this->getFeature() );
			$this->aSubProcessors[ 'wl' ] = $oProc;
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
		$sSlug = $this->getFeature()->getFeatureSlug();

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
	 * @return bool
	 */
	protected function isSecurityAdmin() {
		return self::getController()->getHasPermissionToManage();
	}

	/**
	 * @param int $nId
	 */
	public function restrictAdminUserDelete( $nId ) {
		if ( !$this->isSecurityAdmin() ) {
			$oWpUsers = $this->loadWpUsers();
			$oUser = $oWpUsers->getUserById( $nId );
			if ( $oUser && $oWpUsers->isUserAdmin( $oUser ) ) {
				$this->loadWp()
					 ->wpDie( 'Sorry, deleting administrators is currently restricted to your Security Admin' );
			}
		}
	}

	/**
	 * @param array $aAllCaps
	 * @param       $cap
	 * @param array $aArgs
	 * @return array
	 */
	public function restrictAdminUserChanges( $aAllCaps, $cap, $aArgs ) {
		// If we're registered with Admin Access we don't modify anything
		if ( $this->isSecurityAdmin() ) {
			return $aAllCaps;
		}

		$oWpUsers = $this->loadWpUsers();
		$oDp = $this->loadDataProcessor();

		/** @var string $sRequestedCapability */
		$sRequestedCapability = $aArgs[ 0 ];
		$aUserCapabilities = array( 'edit_users', 'create_users' );

		$bBlockCapability = false;

		if ( in_array( $sRequestedCapability, $aUserCapabilities ) ) {

			// Find the WP_User for the POST
			$oPostUser = false;
			$sPostUserlogin = $oDp->FetchPost( 'user_login' );
			if ( empty( $sPostUserlogin ) ) {
				$nPostUserId = $oDp->FetchPost( 'user_id' );
				if ( !empty( $nPostUserId ) ) {
					$oPostUser = $oWpUsers->getUserById( $nPostUserId );
				}
			}
			else {
				$oPostUser = $oWpUsers->getUserByUsername( $sPostUserlogin );
			}

			$sRequestRole = $oDp->FetchPost( 'role', '' );

			if ( $oPostUser ) {
				// editing an existing user other than yourself?
				if ( $oPostUser->get( 'user_login' ) != $oWpUsers->getCurrentWpUser()->get( 'user_login' ) ) {

					if ( $oWpUsers->isUserAdmin( $oPostUser ) || ( $sRequestRole == 'administrator' ) ) {
						$bBlockCapability = true;
					}
				}
			}
			else {
				//creating a new admin user?
				if ( $sRequestRole == 'administrator' ) {
					$bBlockCapability = true;
				}
			}
		}

		if ( $bBlockCapability ) {
			$aAllCaps[ $sRequestedCapability ] = false;
		}

		return $aAllCaps;
	}

	/**
	 * @param array $aNoticeAttributes
	 */
	public function addNotice_certain_options_restricted( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->doCheckHasPermissionToSubmit() ) {
			return;
		}

		$sCurrentPage = $this->loadWp()->getCurrentPage();
		$sCurrentGetPage = $this->loadDP()->query( 'page' );
		if ( !in_array( $sCurrentPage, $oFO->getOptionsPagesToRestrict() ) || !empty( $sCurrentGetPage ) ) {
			return;
		}

		$sName = $this->getController()->getHumanName();
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
	 */
	public function addNotice_admin_users_restricted( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->doCheckHasPermissionToSubmit() ) {
			return;
		}

		$sCurrentPage = $this->loadWp()->getCurrentPage();
		if ( !in_array( $sCurrentPage, $this->getUserPagesToRestrict() ) ) {
			return;
		}

		$sName = $this->getController()->getHumanName();
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
	 * Right before a plugin option is due to update it will check that we have permissions to do so and if not, will
	 * revert the option to save to the previous one.
	 * @param mixed  $mNewOptionValue
	 * @param string $sOptionKey
	 * @param mixed  $mOldValue
	 * @return mixed
	 */
	public function blockOptionsSaves( $mNewOptionValue, $sOptionKey, $mOldValue ) {

		$bSavingIsPermitted = true;

		if ( $this->isOptionForThisPlugin( $sOptionKey ) || $this->isOptionRestricted( $sOptionKey ) ) {
			$bSavingIsPermitted = $this->isSecurityAdmin();

			if ( !$bSavingIsPermitted ) {
				$this->doStatIncrement( 'option.save.blocked' );
			}
		}

		return $bSavingIsPermitted ? $mNewOptionValue : $mOldValue;
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
		$oFO = $this->getFeature();
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
		// If we're registered with Admin Access we can do everything!
		if ( $this->isSecurityAdmin() ) {
			return $aAllCaps;
		}

		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getFeature();

		/** @var string $sRequestedCapability */
		$sRequestedCapability = $aArgs[ 0 ];
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
		$bHasAdminAccess = self::getController()->getHasPermissionToManage();
		if ( $bHasAdminAccess ) {
			return $aAllCaps;
		}

		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getFeature();

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
		// If we're registered with Admin Access we don't modify anything
		$bHasAdminAccess = self::getController()->getHasPermissionToManage();
		if ( $bHasAdminAccess ) {
			return $aAllCaps;
		}

		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getFeature();

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
				$this->getFeature()->getOptionStoragePrefix()
			);
		}
		return $this->sOptionRegexPattern;
	}

	public function printAdminAccessAjaxForm() {
		/** @var ICWP_WPSF_FeatureHandler_AdminAccessRestriction $oFO */
		$oFO = $this->getFeature();

		if ( $oFO->doCheckHasPermissionToSubmit() ) {
			return;
		}

		$aRenderData = array(
			'strings'     => array(
				'editing_restricted' => _wpsf__( 'Editing this option is currently restricted.' ),
				'unlock_link'        => $this->getUnlockLinkHtml(),
			),
			'sAjaxNonce'  => wp_create_nonce( 'icwp_ajax' ),
			'js_snippets' => array(
				'options_to_restrict' => "'".implode( "','", $oFO->getOptionsToRestrict() )."'",
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
}