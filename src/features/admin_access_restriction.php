<?php

if ( class_exists( 'ICWP_WPSF_FeatureHandler_AdminAccessRestriction' ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_FeatureHandler_AdminAccessRestriction extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	private $bHasPermissionToSubmit;

	/**
	 * @return bool
	 */
	protected function isReadyToExecute() {
		return parent::isReadyToExecute() && !$this->isVisitorWhitelisted();
	}

	protected function adminAjaxHandlers() {
		parent::adminAjaxHandlers();
		add_action( 'wp_ajax_icwp_wpsf_LoadAdminAccessForm', array( $this, 'ajaxLoadAdminAccessForm' ) );
		add_action( $this->prefixWpAjax( 'AdminAccessLogin' ), array( $this, 'ajaxAdminAccessLogin' ) );
	}

	public function ajaxLoadAdminAccessForm() {
		$bSuccess = $this->checkAjaxNonce();
		if ( $bSuccess ) {
			$sResponseData = array();
			$sResponseData[ 'html' ] = $this->renderAdminAccessAjaxLoginForm();
			$this->sendAjaxResponse( true, $sResponseData );
		}
	}

	/**
	 * @param string $sMessage
	 * @return string
	 */
	protected function renderAdminAccessAjaxLoginForm( $sMessage = '' ) {
		$aData = $this->getBaseAjaxActionRenderData( 'AdminAccessLogin' );
		$aData[ 'admin_access_message' ] = empty( $sMessage ) ? _wpsf__( 'Enter your Security Admin Access Key' ) : $sMessage;
		return $this->renderTemplate( 'snippets/admin_access_login', $aData );
	}

	public function ajaxAdminAccessLogin() {

		if ( $this->isValidAjaxRequestForModule() ) {
			$sResponseData = array();
			$bSuccess = $this->checkAdminAccessKeySubmission();
			if ( $bSuccess ) {
				$this->setPermissionToSubmit( true );
				$sResponseData[ 'html' ] = _wpsf__( 'Security Admin Access Key Accepted.' ).' '._wpsf__( 'Please wait' ).' ...';
			}
			else {
				$sResponseData[ 'html' ] = $this->renderAdminAccessAjaxLoginForm( _wpsf__( 'Error - Invalid Key' ) );
			}
			$this->sendAjaxResponse( $bSuccess, $sResponseData );
		}
	}

	/**
	 * @param bool $fHasPermission
	 * @return bool
	 */
	public function doCheckHasPermissionToSubmit( $fHasPermission = true ) {

		// We don't use setPermissionToSubmit() here because of timing with headers - we just for now manually
		// checking POST for the submission of the key and if it fits, we say "yes"
		if ( $this->checkAdminAccessKeySubmission() ) {
			$this->bHasPermissionToSubmit = true;
		}

		if ( isset( $this->bHasPermissionToSubmit ) ) {
			return $this->bHasPermissionToSubmit;
		}

		$this->bHasPermissionToSubmit = $fHasPermission;
		if ( $this->getIsMainFeatureEnabled() ) {

			$sAccessKey = $this->getOpt( 'admin_access_key' );
			if ( !empty( $sAccessKey ) ) {
				$oDp = $this->loadDP();
				$sCookieValue = $oDp->cookie( $this->getSecurityAdminCookieName() );
				$this->bHasPermissionToSubmit = ( $sCookieValue === md5( $sAccessKey ) );
			}
		}
		return $this->bHasPermissionToSubmit;
	}

	/** TODO
	 * @return bool
	 */
	public function getAdminAccessArea_Options() {
		return $this->getOptIs( 'admin_access_restrict_options', 'Y' );
	}

	/**
	 * @return array
	 */
	public function getAdminAccessArea_Plugins() {
		return $this->getAdminAccessArea( 'plugins' );
	}

	/**
	 * @return array
	 */
	public function getAdminAccessArea_Themes() {
		return $this->getAdminAccessArea( 'themes' );
	}

	/**
	 * @return array
	 */
	public function getAdminAccessArea_Posts() {
		return $this->getAdminAccessArea( 'posts' );
	}

	/**
	 * @param string $sArea one of plugins, themes
	 * @return array
	 */
	public function getAdminAccessArea( $sArea = 'plugins' ) {
		$aSettings = $this->getOpt( 'admin_access_restrict_'.$sArea, array() );
		return !is_array( $aSettings ) ? array() : $aSettings;
	}

	/**
	 * @return array
	 */
	public function getRestrictedOptions() {
		$aOptions = $this->getDefinition( 'admin_access_options_to_restrict' );
		return is_array( $aOptions ) ? $aOptions : array();
	}

	/**
	 * TODO: Bug where if $sType is defined, it'll be set to 'wp' anyway
	 * @param string $sType - wp or wpms
	 * @return array
	 */
	public function getOptionsToRestrict( $sType = '' ) {
		$sType = empty( $sType ) ? ( $this->loadWp()->isMultisite() ? 'wpms' : 'wp' ) : 'wp';
		$aOptions = $this->getRestrictedOptions();
		return ( isset( $aOptions[ $sType.'_options' ] ) && is_array( $aOptions[ $sType.'_options' ] ) ) ? $aOptions[ $sType.'_options' ] : array();
	}

	/**
	 * @param string $sType - wp or wpms
	 * @return array
	 */
	public function getOptionsPagesToRestrict( $sType = '' ) {
		$sType = empty( $sType ) ? ( $this->loadWp()->isMultisite() ? 'wpms' : 'wp' ) : 'wp';
		$aOptions = $this->getRestrictedOptions();
		return ( isset( $aOptions[ $sType.'_pages' ] ) && is_array( $aOptions[ $sType.'_pages' ] ) ) ? $aOptions[ $sType.'_pages' ] : array();
	}

	/**
	 */
	protected function setAdminAccessCookie() {
		$sAccessKey = $this->getOpt( 'admin_access_key' );
		if ( !empty( $sAccessKey ) ) {
			$this->loadDP()
				 ->setCookie(
					 $this->getSecurityAdminCookieName(),
					 md5( $sAccessKey ),
					 $this->getOpt( 'admin_access_timeout' )*60
				 );
		}
	}

	/**
	 */
	protected function clearAdminAccessCookie() {
		$this->loadDataProcessor()->setDeleteCookie( $this->getSecurityAdminCookieName() );
	}

	/**
	 */
	protected function doExtraSubmitProcessing() {
		// We should only use setPermissionToSubmit() here, before any headers elsewhere are sent out.
		if ( $this->checkAdminAccessKeySubmission() ) {
			$this->setPermissionToSubmit( true );
//			wp_safe_redirect( network_admin_url() );
		}
	}

	/**
	 * @return string
	 */
	public function getSecurityAdminCookieName() {
		return $this->getDefinition( 'security_admin_cookie_name' );
	}

	/**
	 * @param bool $fPermission
	 */
	public function setPermissionToSubmit( $fPermission = false ) {
		if ( $fPermission ) {
			$this->setAdminAccessCookie();
		}
		else {
			$this->clearAdminAccessCookie();
		}
	}

	/**
	 * @return bool
	 */
	protected function checkAdminAccessKeySubmission() {
		$sAccessKeyRequest = $this->loadDP()->post( 'admin_access_key_request' );
		$bSuccess = $this->verifyAccessKey( $sAccessKeyRequest );
		if ( !$bSuccess && !empty( $sAccessKeyRequest ) ) {
			add_filter( $this->prefix( 'ip_black_mark' ), '__return_true' );
		}
		return $bSuccess;
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function verifyAccessKey( $sKey ) {
		return !empty( $sKey ) && ( $this->getOpt( 'admin_access_key' ) === md5( $sKey ) );
	}

	/**
	 * @param string $sKey
	 * @return $this
	 * @throws Exception
	 */
	public function setNewAccessKeyManually( $sKey ) {
		if ( !$this->doCheckHasPermissionToSubmit() ) {
			throw new Exception( 'User does not have permission to update the Security Admin Access Key.' );
		}
		if ( empty( $sKey ) ) {
			throw new Exception( 'Attempting to set an empty Security Admin Access Key.' );
		}

		$this->setIsMainFeatureEnabled( true )
			 ->setOpt( 'admin_access_key', md5( $sKey ) )
			 ->savePluginOptions();
		return $this;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_admin_access_restriction' :
				$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Restricts access to this plugin preventing unauthorized changes to your security settings.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Security Admin' ) ) ),
					sprintf( _wpsf__( 'You need to also enter a new Access Key to enable this feature.' ) ),
				);
				$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_admin_access_restriction_settings' :
				$sTitle = _wpsf__( 'Security Admin Restriction Settings' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Restrict access using a simple Access Key.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use of this feature is highly recommend.' ) ),
				);
				$sTitleShort = _wpsf__( 'Security Admin Settings' );
				break;

			case 'section_admin_access_restriction_areas' :
				$sTitle = _wpsf__( 'Security Admin Restriction Zones' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Restricts access to key WordPress areas for all users not authenticated with the Security Admin Access system.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use of this feature is highly recommend.' ) ),
				);
				$sTitleShort = _wpsf__( 'Access Restriction Zones' );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		switch ( $sKey ) {

			case 'enable_admin_access_restriction' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__( 'Security Admin' ) );
				$sSummary = _wpsf__( 'Enforce Security Admin Access Restriction' );
				$sDescription = _wpsf__( 'Enable this with great care and consideration. When this Access Key option is enabled, you must specify a key below and use it to gain access to this plugin.' );
				break;

			case 'admin_access_key' :
				$sName = _wpsf__( 'Security Admin Access Key' );
				$sSummary = _wpsf__( 'Provide/Update Security Admin Access Key' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'If you forget this, you could potentially lock yourself out from using this plugin.' ) );
				break;

			case 'admin_access_timeout' :
				$sName = _wpsf__( 'Security Admin Timeout' );
				$sSummary = _wpsf__( 'Specify An Automatic Timeout Interval For Security Admin Access' );
				$sDescription = _wpsf__( 'This will automatically expire your Security Admin Session.' )
								.' '._wpsf__( 'Does not apply until you enter the access key again.' )
								.'<br />'.sprintf( _wpsf__( 'Default: %s minutes.' ), $this->getOptionsVo()
																						   ->getOptDefault( 'admin_access_timeout' ) );
				break;

			case 'admin_access_restrict_posts' :
				$sName = _wpsf__( 'Pages' );
				$sSummary = _wpsf__( 'Restrict Access To Key WordPress Posts And Pages Actions' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'This will restrict access to page/post creation, editing and deletion.' ) )
								.'<br />'.sprintf( _wpsf__( 'Note: %s' ), sprintf( _wpsf__( 'Selecting "%s" will also restrict all other options.' ), _wpsf__( 'Edit' ) ) );
				break;

			case 'admin_access_restrict_plugins' :
				$sName = _wpsf__( 'Plugins' );
				$sSummary = _wpsf__( 'Restrict Access To Key WordPress Plugin Actions' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'This will restrict access to plugin installation, update, activation and deletion.' ) )
								.'<br />'.sprintf( _wpsf__( 'Note: %s' ), sprintf( _wpsf__( 'Selecting "%s" will also restrict all other options.' ), _wpsf__( 'Activate' ) ) );
				break;

			case 'admin_access_restrict_options' :
				$sName = _wpsf__( 'WordPress Options' );
				$sSummary = _wpsf__( 'Restrict Access To Certain WordPress Admin Options' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'This will restrict the ability of WordPress administrators from changing key WordPress settings.' ) );
				break;

			case 'admin_access_restrict_admin_users' :
				$sName = _wpsf__( 'Admin Users' );
				$sSummary = _wpsf__( 'Restrict Access To Create/Delete/Modify Other Admin Users' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'This will restrict the ability of WordPress administrators from creating, modifying or promoting other administrators.' ) );
				break;

			case 'admin_access_restrict_themes' :
				$sName = _wpsf__( 'Themes' );
				$sSummary = _wpsf__( 'Restrict Access To WordPress Theme Actions' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'This will restrict access to theme installation, update, activation and deletion.' ) )
								.'<br />'.
								sprintf(
									_wpsf__( 'Note: %s' ),
									sprintf(
										_wpsf__( 'Selecting "%s" will also restrict all other options.' ),
										sprintf(
											_wpsf__( '%s and %s' ),
											_wpsf__( 'Activate' ),
											_wpsf__( 'Edit Theme Options' )
										)
									)
								);
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams[ 'name' ] = $sName;
		$aOptionsParams[ 'summary' ] = $sSummary;
		$aOptionsParams[ 'description' ] = $sDescription;
		return $aOptionsParams;
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {

		if ( $this->getOpt( 'admin_access_timeout' ) < 1 ) {
			$this->getOptionsVo()->resetOptToDefault( 'admin_access_timeout' );
		}

		$sAccessKey = $this->getOpt( 'admin_access_key' );
		if ( empty( $sAccessKey ) ) {
			$this->setOpt( 'enable_admin_access_restriction', 'N' );
		}

		// Restricting Activate Plugins also means restricting the rest.
		$aPluginsRestrictions = $this->getAdminAccessArea_Plugins();
		if ( in_array( 'activate_plugins', $aPluginsRestrictions ) ) {
			$this->setOpt(
				'admin_access_restrict_plugins',
				array_unique( array_merge( $aPluginsRestrictions, array(
					'install_plugins',
					'update_plugins',
					'delete_plugins'
				) ) )
			);
		}

		// Restricting Switch (Activate) Themes also means restricting the rest.
		$aThemesRestrictions = $this->getAdminAccessArea_Themes();
		if ( in_array( 'switch_themes', $aThemesRestrictions ) && in_array( 'edit_theme_options', $aThemesRestrictions ) ) {
			$this->setOpt(
				'admin_access_restrict_themes',
				array_unique( array_merge( $aThemesRestrictions, array(
					'install_themes',
					'update_themes',
					'delete_themes'
				) ) )
			);
		}

		$aPostRestrictions = $this->getAdminAccessArea_Posts();
		if ( in_array( 'edit', $aPostRestrictions ) ) {
			$this->setOpt(
				'admin_access_restrict_posts',
				array_unique( array_merge( $aPostRestrictions, array( 'create', 'publish', 'delete' ) ) )
			);
		}
	}
}