<?php

if ( !class_exists('ICWP_WPSF_FeatureHandler_AdminAccessRestriction') ):

require_once( dirname(__FILE__).ICWP_DS.'base.php' );

class ICWP_WPSF_FeatureHandler_AdminAccessRestriction extends ICWP_WPSF_FeatureHandler_Base {

	private $bHasPermissionToSubmit;

	protected function doExecuteProcessor() {
		if ( ! apply_filters( $this->doPluginPrefix( 'visitor_is_whitelisted' ), false ) ) {
			parent::doExecuteProcessor();
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

		$oDp = $this->loadDataProcessor();

		$this->bHasPermissionToSubmit = $fHasPermission;
		if ( $this->getIsMainFeatureEnabled() )  {

			$sAccessKey = $this->getOpt( 'admin_access_key' );
			if ( !empty( $sAccessKey ) ) {
				$sHash = md5( $sAccessKey );
				$sCookieValue = $oDp->FetchCookie( $this->getAdminAccessKeyCookieName() );
				$this->bHasPermissionToSubmit = ( $sCookieValue === $sHash );
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
	public function getOptionsToRestrict() {
		return $this->getOpt( 'admin_access_options_to_restrict', array() );
	}

	/**
	 */
	protected function setAdminAccessCookie() {
		$sAccessKey = $this->getOpt( 'admin_access_key' );
		if ( !empty( $sAccessKey ) ) {
			$sValue = md5( $sAccessKey );
			$sTimeout = $this->getOpt( 'admin_access_timeout' ) * 60;
			$_COOKIE[ $this->getAdminAccessKeyCookieName() ] = $sValue;
			setcookie( $this->getAdminAccessKeyCookieName(), $sValue, time()+$sTimeout, COOKIEPATH, COOKIE_DOMAIN, false );
		}
	}

	/**
	 */
	protected function clearAdminAccessCookie() {
		unset( $_COOKIE[ $this->getAdminAccessKeyCookieName() ] );
		setcookie( $this->getAdminAccessKeyCookieName(), "", time()-3600, COOKIEPATH, COOKIE_DOMAIN, false );
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
	public function getAdminAccessKeyCookieName() {
		return $this->getOpt( 'admin_access_key_cookie_name' );
	}

	/**
	 * @param bool $fPermission
	 */
	protected function setPermissionToSubmit( $fPermission = false ) {
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
		$oDp = $this->loadDataProcessor();

		$sAccessKeyRequest = $oDp->FetchPost( $this->doPluginPrefix( 'admin_access_key_request', '_' ) );
		if ( empty( $sAccessKeyRequest ) ) {
			return false;
		}
		$bSuccess = ( $this->getOpt( 'admin_access_key' ) === md5( $sAccessKeyRequest ) );
		if ( !$bSuccess ) {
			add_filter( $this->doPluginPrefix( 'ip_black_mark' ), '__return_true' );
		}
		return $bSuccess;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams['section_slug'];
		switch( $aOptionsParams['section_slug'] ) {

			case 'section_enable_plugin_feature_admin_access_restriction' :
				$sTitle = sprintf( _wpsf__( 'Enable Plugin Feature: %s' ), $this->getMainFeatureName() );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Restricts access to this plugin preventing unauthorized changes to your security settings.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), sprintf( _wpsf__( 'Keep the %s feature turned on.' ), _wpsf__( 'Admin Access' ) ) ),
					sprintf( _wpsf__( 'You need to also enter a new Access Key to enable this feature.' ) ),
				);
				$sTitleShort = sprintf( '%s / %s', _wpsf__( 'Enable' ), _wpsf__( 'Disable' ) );
				break;

			case 'section_admin_access_restriction_settings' :
				$sTitle = _wpsf__( 'Admin Access Restriction Settings' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Restrict access using a simple Access Key.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use of this feature is highly recommend.' ) ),
				);
				$sTitleShort = _wpsf__( 'Access Restriction Settings' );
				break;

			case 'section_admin_access_restriction_areas' :
				$sTitle = _wpsf__( 'Admin Access Restriction Areas' );
				$aSummary = array(
					sprintf( _wpsf__( 'Purpose - %s' ), _wpsf__( 'Restricts access to key WordPress areas for all users not authenticated with the Admin Access system.' ) ),
					sprintf( _wpsf__( 'Recommendation - %s' ), _wpsf__( 'Use of this feature is highly recommend.' ) ),
				);
				$sTitleShort = _wpsf__( 'Access Restriction Areas' );
				break;

			default:
				throw new Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams['section_title'] = $sTitle;
		$aOptionsParams['section_summary'] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : array();
		$aOptionsParams['section_title_short'] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams['key'];
		switch( $sKey ) {

			case 'enable_admin_access_restriction' :
				$sName = sprintf( _wpsf__( 'Enable %s' ), _wpsf__('Admin Access') );
				$sSummary = _wpsf__( 'Enforce Admin Access Restriction' );
				$sDescription = _wpsf__( 'Enable this with great care and consideration. When this Access Key option is enabled, you must specify a key below and use it to gain access to this plugin.' );
				break;

			case 'admin_access_key' :
				$sName = _wpsf__( 'Admin Access Key' );
				$sSummary = _wpsf__( 'Provide/Update Admin Access Key' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'If you forget this, you could potentially lock yourself out from using this plugin.' ) );
				break;


			case 'admin_access_timeout' :
				$sName = _wpsf__( 'Admin Access Timeout' );
				$sSummary = _wpsf__( 'Specify An Automatic Timeout Interval For Admin Access' );
				$sDescription = sprintf( _wpsf__( 'This will automatically expire your %s Admin Access Session.'), $this->getController()->getHumanName() )
					.' '._wpsf__( 'Does not apply until you enter the access key again.' )
					.'<br />'.sprintf( _wpsf__( 'Default: %s minutes.' ), $this->getOptionsVo()->getOptDefault( 'admin_access_timeout' ) );
				break;

			case 'admin_access_restrict_posts' :
				$sName = _wpsf__( 'Admin Access Pages' );
				$sSummary = _wpsf__( 'Restrict Access To Key WordPress Posts And Pages Actions' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'This will restrict access to page/post creation, editing and deletion.' ) )
								.'<br />'.sprintf(_wpsf__( 'Note: %s' ), sprintf( _wpsf__( 'Selecting "%s" will also restrict all other options.' ), _wpsf__('Edit') ) );
				break;

			case 'admin_access_restrict_plugins' :
				$sName = _wpsf__( 'Admin Access Plugins' );
				$sSummary = _wpsf__( 'Restrict Access To Key WordPress Plugin Actions' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'This will restrict access to plugin installation, update, activation and deletion.' ) )
								.'<br />'.sprintf(_wpsf__( 'Note: %s' ), sprintf( _wpsf__( 'Selecting "%s" will also restrict all other options.' ), _wpsf__('Activate') ) );
				break;

			case 'admin_access_restrict_options' :
				$sName = _wpsf__( 'Admin Access Options' );
				$sSummary = _wpsf__( 'Restrict Access To Changing Admin Options' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'This will restrict access to plugin installation, update, activation and deletion.' ) )
								.'<br />'.sprintf(_wpsf__( 'Note: %s' ), sprintf( _wpsf__( 'Selecting "%s" will also restrict all other options.' ), _wpsf__('Activate') ) );
				break;

			case 'admin_access_restrict_themes' :
				$sName = _wpsf__( 'Admin Access Themes' );
				$sSummary = _wpsf__( 'Restrict Access To WordPress Theme Actions' );
				$sDescription = sprintf( _wpsf__( 'Careful: %s' ), _wpsf__( 'This will restrict access to theme installation, update, activation and deletion.' ) )
								.'<br />'.
								sprintf(
									_wpsf__( 'Note: %s' ),
									sprintf(
										_wpsf__( 'Selecting "%s" will also restrict all other options.' ),
										sprintf(
											_wpsf__('%s and %s'),
											_wpsf__( 'Activate' ),
											_wpsf__( 'Edit Theme Options' )
										)
									)
								);
				break;

			default:
				throw new Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
		}

		$aOptionsParams['name'] = $sName;
		$aOptionsParams['summary'] = $sSummary;
		$aOptionsParams['description'] = $sDescription;
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
			$aPluginsRestrictions = array_merge( $aPluginsRestrictions, array( 'install_plugins', 'update_plugins', 'delete_plugins' ) );
			$this->setOpt( 'admin_access_restrict_plugins', $aPluginsRestrictions );
		}

		// Restricting Switch (Activate) Themes also means restricting the rest.
		$aThemesRestrictions = $this->getAdminAccessArea_Themes();
		if ( in_array( 'switch_themes', $aThemesRestrictions ) && in_array( 'edit_theme_options', $aThemesRestrictions ) ) {
			$aThemesRestrictions = array_merge( $aThemesRestrictions, array( 'install_themes', 'update_themes', 'delete_themes' ) );
			$this->setOpt( 'admin_access_restrict_themes', $aThemesRestrictions );
		}

		$aPostRestrictions = $this->getAdminAccessArea_Posts();
		if ( in_array( 'edit', $aPostRestrictions ) ) {
			$aThemesRestrictions = array_merge( $aPostRestrictions, array( 'create', 'publish', 'delete' ) );
			$this->setOpt( 'admin_access_restrict_posts', $aThemesRestrictions );
		}
	}

	protected function updateHandler() {
		parent::updateHandler();

		if ( $this->getVersion() == '0.0' ) {
			return;
		}

		if ( version_compare( $this->getVersion(), '3.0.0', '<' ) ) {
			$aAllOptions = apply_filters( $this->doPluginPrefix( 'aggregate_all_plugin_options' ), array() );
			$this->setOpt( 'enable_admin_access_restriction', $aAllOptions['enable_admin_access_restriction'] );
			$this->setOpt( 'admin_access_key', $aAllOptions['admin_access_key'] );
			$this->setOpt( 'admin_access_timeout', $aAllOptions['admin_access_timeout'] );
		}
	}
}
endif;