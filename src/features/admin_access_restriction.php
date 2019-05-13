<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_AdminAccessRestriction extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	const HASH_DELETE = '32f68a60cef40faedbc6af20298c1a1e';

	/**
	 */
	protected function setupCustomHooks() {
		add_action( $this->prefix( 'pre_deactivate_plugin' ), [ $this, 'preDeactivatePlugin' ] );
	}

	/**
	 * @return bool
	 */
	protected function isReadyToExecute() {
		return $this->isEnabledSecurityAdmin() && parent::isReadyToExecute();
	}

	/**
	 * @param array $aAjaxResponse
	 * @return array
	 */
	public function handleAuthAjax( $aAjaxResponse ) {

		if ( empty( $aAjaxResponse ) ) {
			switch ( Services::Request()->request( 'exec' ) ) {

				case 'sec_admin_check':
					$aAjaxResponse = $this->ajaxExec_SecAdminCheck();
					break;

				case 'sec_admin_login':
				case 'restricted_access':
					$aAjaxResponse = $this->ajaxExec_SecAdminLogin();
					break;

				case 'sec_admin_login_box':
					$aAjaxResponse = $this->ajaxExec_SecAdminLoginBox();
					break;

					break;

				default:
					break;
			}
		}
		return parent::handleAuthAjax( $aAjaxResponse );
	}

	/**
	 * @return array
	 */
	private function ajaxExec_SecAdminCheck() {
		return [
			'timeleft' => $this->getSecAdminTimeLeft(),
			'success'  => $this->isSecAdminSessionValid()
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_SecAdminLogin() {
		$bSuccess = false;
		$sHtml = '';

		if ( $this->checkAdminAccessKeySubmission() ) {

			if ( $this->setSecurityAdminStatusOnOff( true ) ) {
				$bSuccess = true;
				$sMsg = __( 'Security Admin Access Key Accepted.', 'wp-simple-firewall' )
						.' '.__( 'Please wait', 'wp-simple-firewall' ).' ...';
			}
			else {
				$sMsg = __( 'Failed to process key - you may need to re-login to WordPress.', 'wp-simple-firewall' );
			}
		}
		else {
			/** @var ICWP_WPSF_Processor_Ips $oIpPro */
			$oIpPro = $this->getCon()
						   ->getModule( 'ips' )
						   ->getProcessor();
			$nRemaining = $oIpPro->getRemainingTransgressions() - 1;
			$sMsg = __( 'Security access key incorrect.', 'wp-simple-firewall' ).' ';
			if ( $nRemaining > 0 ) {
				$sMsg .= sprintf( __( 'Attempts remaining: %s.', 'wp-simple-firewall' ), $nRemaining );
			}
			else {
				$sMsg .= __( "No attempts remaining.", 'wp-simple-firewall' );
			}
			$sHtml = $this->renderAdminAccessAjaxLoginForm( $sMsg );
		}

		return [
			'success'     => $bSuccess,
			'page_reload' => true,
			'message'     => $sMsg,
			'html'        => $sHtml,
		];
	}

	/**
	 * @return array
	 */
	private function ajaxExec_SecAdminLoginBox() {
		return [
			'success' => 'true',
			'html'    => $this->renderAdminAccessAjaxLoginForm()
		];
	}

	/**
	 * @param string $sMessage
	 * @return string
	 */
	protected function renderAdminAccessAjaxLoginForm( $sMessage = '' ) {

		$aData = [
			'ajax'    => [
				'sec_admin_login' => json_encode( $this->getSecAdminLoginAjaxData() )
			],
			'strings' => [
				'access_message' => empty( $sMessage ) ? __( 'Enter your Security Admin Access Key', 'wp-simple-firewall' ) : $sMessage
			]
		];
		return $this->renderTemplate( 'snippets/admin_access_login', $aData );
	}

	/**
	 * @return string
	 */
	protected function getAccessKeyHash() {
		return $this->getOpt( 'admin_access_key' );
	}

	/**
	 * @return bool
	 */
	public function getAdminAccessArea_Options() {
		return $this->isOpt( 'admin_access_restrict_options', 'Y' );
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
		$aSettings = $this->getOpt( 'admin_access_restrict_'.$sArea, [] );
		return !is_array( $aSettings ) ? [] : $aSettings;
	}

	/**
	 * @return array
	 */
	public function getRestrictedOptions() {
		$aOptions = $this->getDef( 'admin_access_options_to_restrict' );
		return is_array( $aOptions ) ? $aOptions : [];
	}

	/**
	 * @return array
	 */
	public function getSecurityAdminUsers() {
		$aU = $this->getOpt( 'sec_admin_users', [] );
		return ( is_array( $aU ) && $this->isPremium() ) ? $aU : [];
	}

	/**
	 * TODO: Bug where if $sType is defined, it'll be set to 'wp' anyway
	 * @param string $sType - wp or wpms
	 * @return array
	 */
	public function getOptionsToRestrict( $sType = '' ) {
		$sType = empty( $sType ) ? ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ) : 'wp';
		$aOptions = $this->getRestrictedOptions();
		return ( isset( $aOptions[ $sType.'_options' ] ) && is_array( $aOptions[ $sType.'_options' ] ) ) ? $aOptions[ $sType.'_options' ] : [];
	}

	/**
	 * @param string $sType - wp or wpms
	 * @return array
	 */
	public function getOptionsPagesToRestrict( $sType = '' ) {
		$sType = empty( $sType ) ? ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ) : 'wp';
		$aOptions = $this->getRestrictedOptions();
		return ( isset( $aOptions[ $sType.'_pages' ] ) && is_array( $aOptions[ $sType.'_pages' ] ) ) ? $aOptions[ $sType.'_pages' ] : [];
	}

	/**
	 * @return bool
	 */
	public function hasAccessKey() {
		$sKey = $this->getAccessKeyHash();
		return !empty( $sKey ) && strlen( $sKey ) == 32;
	}

	/**
	 * @return bool
	 */
	public function hasSecAdminUsers() {
		$aUsers = $this->getSecurityAdminUsers();
		return !empty( $aUsers );
	}

	/**
	 * No checking of admin capabilities in-case of infinite loop with admin access caps check
	 * @return bool
	 */
	public function isRegisteredSecAdminUser() {
		$sUser = Services::WpUsers()->getCurrentWpUsername();
		return !empty( $sUser ) && in_array( $sUser, $this->getSecurityAdminUsers() );
	}

	/**
	 * @return bool
	 */
	public function isAdminAccessAdminUsersEnabled() {
		return $this->isOpt( 'admin_access_restrict_admin_users', 'Y' );
	}

	/**
	 */
	protected function doExtraSubmitProcessing() {
		if ( $this->isAccessKeyRequest() && $this->checkAdminAccessKeySubmission() ) {
			$this->setSecurityAdminStatusOnOff( true );
		}

		// Verify whitelabel images
		if ( $this->isWlEnabled() ) {
			$aImages = [
				'wl_menuiconurl',
				'wl_dashboardlogourl',
				'wl_login2fa_logourl',
			];
			$oDP = $this->loadDP();
			$oOpts = $this->getOptionsVo();
			foreach ( $aImages as $sKey ) {
				if ( !$oDP->isValidUrl( $this->buildWlImageUrl( $sKey ) ) ) {
					$oOpts->resetOptToDefault( $sKey );
				}
			}
		}

		$this->setOpt( 'sec_admin_users', $this->verifySecAdminUsers( $this->getSecurityAdminUsers() ) );
	}

	/**
	 * Ensures that all entries are valid users.
	 * @param string[] $aSecUsers
	 * @return string[]
	 */
	private function verifySecAdminUsers( $aSecUsers ) {
		$oDP = Services::Data();
		$oWpUsers = Services::WpUsers();

		$aFiltered = [];
		foreach ( $aSecUsers as $nCurrentKey => $sUsernameOrEmail ) {
			if ( $oDP->validEmail( $sUsernameOrEmail ) ) {
				$oUser = $oWpUsers->getUserByEmail( $sUsernameOrEmail );
			}
			else {
				$oUser = $oWpUsers->getUserByUsername( $sUsernameOrEmail );
				if ( is_null( $oUser ) && is_numeric( $sUsernameOrEmail ) ) {
					$oUser = $oWpUsers->getUserById( $sUsernameOrEmail );
				}
			}

			if ( $oUser instanceof WP_User && $oUser->ID > 0 && $oWpUsers->isUserAdmin( $oUser ) ) {
				$aFiltered[] = $oUser->user_login;
			}
		}

		// We now run a bit of a sanity check to ensure that the current user is
		// not adding users here that aren't themselves without a key to still gain access
		$oCurrent = $oWpUsers->getCurrentWpUser();
		if ( !empty( $aFiltered ) && !$this->hasAccessKey() && !in_array( $oCurrent->user_login, $aFiltered ) ) {
			$aFiltered[] = $oCurrent->user_login;
		}

		natsort( $aFiltered );
		return array_unique( $aFiltered );
	}

	protected function setSaveUserResponse() {
		if ( $this->isAccessKeyRequest() ) {
			$bSuccess = $this->checkAdminAccessKeySubmission();

			if ( $bSuccess ) {
				$sMessage = __( 'Security Admin key accepted.', 'wp-simple-firewall' );
			}
			else {
				$sMessage = __( 'Security Admin key not accepted.', 'wp-simple-firewall' );
			}
			$this->setFlashAdminNotice( $sMessage, $bSuccess );
		}
		else {
			parent::setSaveUserResponse();
		}
	}

	/**
	 * @return int
	 */
	public function getSecAdminTimeout() {
		return (int)$this->getOpt( 'admin_access_timeout' )*MINUTE_IN_SECONDS;
	}

	/**
	 * Only returns greater than 0 if you have a valid Sec admin session
	 * @return int
	 */
	public function getSecAdminTimeLeft() {
		$nLeft = 0;
		if ( $this->hasSession() ) {

			$nSecAdminAt = $this->getSession()->getSecAdminAt();
			if ( $this->isRegisteredSecAdminUser() ) {
				$nLeft = 0;
			}
			else if ( $nSecAdminAt > 0 ) {
				$nLeft = $this->getSecAdminTimeout() - ( Services::Request()->ts() - $nSecAdminAt );
			}
		}
		return max( 0, $nLeft );
	}

	/**
	 * @return bool
	 */
	public function isSecAdminSessionValid() {
		return ( $this->getSecAdminTimeLeft() > 0 );
	}

	/**
	 * @return bool
	 */
	public function isEnabledSecurityAdmin() {
		return $this->isModOptEnabled() &&
			   ( $this->hasSecAdminUsers() ||
				 ( $this->hasAccessKey() && $this->getSecAdminTimeout() > 0 )
			   );
	}

	/**
	 * @param bool $bSetOn
	 * @return bool
	 */
	public function setSecurityAdminStatusOnOff( $bSetOn = false ) {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\Databases\Session\Update $oUpdater */
		$oUpdater = $this->getSessionsProcessor()
						 ->getDbHandler()
						 ->getQueryUpdater();
		return $bSetOn ?
			$oUpdater->startSecurityAdmin( $this->getSession() )
			: $oUpdater->terminateSecurityAdmin( $this->getSession() );
	}

	/**
	 * @return bool
	 */
	public function checkAdminAccessKeySubmission() {
		$bSuccess = false;
		$oReq = Services::Request();
		$sAccessKeyRequest = $oReq->post( 'admin_access_key_request', '' );
		if ( !empty( $sAccessKeyRequest ) ) {
			// Made the hither-to unknown discovery that WordPress magic quotes all $_POST variables
			// So the Admin Password initially provided may have been escaped with "\"
			// The 1st approach uses raw, unescaped. The 2nd approach uses the older escaped $_POST.
			$bSuccess = $this->verifyAccessKey( $sAccessKeyRequest )
						|| $this->verifyAccessKey( $oReq->post( 'admin_access_key_request', '' ) );
			if ( !$bSuccess ) {
				$this->setIpTransgressed();
			}
		}
		return $bSuccess;
	}

	/**
	 * @return bool
	 */
	protected function isAccessKeyRequest() {
		return strlen( Services::Request()->post( 'admin_access_key_request', '' ) ) > 0;
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function verifyAccessKey( $sKey ) {
		return !empty( $sKey ) && ( $this->getAccessKeyHash() === md5( $sKey ) );
	}

	/**
	 * @return array
	 */
	public function getWhitelabelOptions() {
		$sMain = $this->getOpt( 'wl_pluginnamemain' );
		$sMenu = $this->getOpt( 'wl_namemenu' );
		if ( empty( $sMenu ) ) {
			$sMenu = $sMain;
		}

		return [
			'name_main'            => $sMain,
			'name_menu'            => $sMenu,
			'name_company'         => $this->getOpt( 'wl_companyname' ),
			'description'          => $this->getOpt( 'wl_description' ),
			'url_home'             => $this->getOpt( 'wl_homeurl' ),
			'url_icon'             => $this->buildWlImageUrl( 'wl_menuiconurl' ),
			'url_dashboardlogourl' => $this->buildWlImageUrl( 'wl_dashboardlogourl' ),
			'url_login2fa_logourl' => $this->buildWlImageUrl( 'wl_login2fa_logourl' ),
		];
	}

	/**
	 * We cater for 3 options:
	 * Full URL
	 * Relative path URL: i.e. starts with /
	 * Or Plugin image URL i.e. doesn't start with HTTP or /
	 * @param string $sKey
	 * @return string
	 */
	private function buildWlImageUrl( $sKey ) {
		$oDp = $this->loadDP();
		$oOpts = $this->getOptionsVo();

		$sLogoUrl = $this->getOpt( $sKey );
		if ( empty( $sLogoUrl ) ) {
			$oOpts->resetOptToDefault( $sKey );
			$sLogoUrl = $this->getOpt( $sKey );
		}
		if ( !empty( $sLogoUrl ) && !$oDp->isValidUrl( $sLogoUrl ) && strpos( $sLogoUrl, '/' ) !== 0 ) {
			$sLogoUrl = $this->getCon()->getPluginUrl_Image( $sLogoUrl );
			if ( empty( $sLogoUrl ) ) {
				$oOpts->resetOptToDefault( $sKey );
				$sLogoUrl = $this->getCon()->getPluginUrl_Image( $this->getOpt( $sKey ) );
			}
		}

		return $sLogoUrl;
	}

	/**
	 * @return bool
	 */
	public function isWlEnabled() {
		return $this->isOpt( 'whitelabel_enable', 'Y' ) && $this->isPremium();
	}

	/**
	 * @return bool
	 */
	public function isWlHideUpdates() {
		return $this->isWlEnabled() && $this->isOpt( 'wl_hide_updates', 'Y' );
	}

	/**
	 * @param string $sKey
	 * @return $this
	 * @throws \Exception
	 */
	public function setNewAccessKeyManually( $sKey ) {
		if ( empty( $sKey ) ) {
			throw new \Exception( 'Attempting to set an empty Security Admin Access Key.' );
		}
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( 'User does not have permission to update the Security Admin Access Key.' );
		}

		$this->setIsMainFeatureEnabled( true )
			 ->setOpt( 'admin_access_key', md5( $sKey ) )
			 ->savePluginOptions();
		return $this;
	}

	/**
	 * @return $this
	 */
	protected function clearAdminAccessKey() {
		return $this->setOpt( 'admin_access_key', '' );
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		if ( $this->getSecAdminTimeLeft() > 0 ) {
			wp_localize_script(
				$this->prefix( 'plugin' ),
				'icwp_wpsf_vars_secadmin',
				[
					'reqajax'      => $this->getSecAdminCheckAjaxData(),
					'is_sec_admin' => true, // if $nSecTimeLeft > 0
					'timeleft'     => $this->getSecAdminTimeLeft(), // JS uses milliseconds
					'strings'      => [
						'confirm' => __( 'Security Admin session has timed-out.', 'wp-simple-firewall' ).' '.__( 'Reload now?', 'wp-simple-firewall' ),
						'nearly'  => __( 'Security Admin session has nearly timed-out.', 'wp-simple-firewall' ),
						'expired' => __( 'Security Admin session has timed-out.', 'wp-simple-firewall' )
					]
				]
			);
		}
	}

	/**
	 * @param array $aAllData
	 * @return array
	 */
	public function addInsightsConfigData( $aAllData ) {
		$aThis = [
			'strings'      => [
				'title' => __( 'Security Admin', 'wp-simple-firewall' ),
				'sub'   => sprintf( __( 'Prevent Tampering With %s Settings', 'wp-simple-firewall' ), $this->getCon()
																										   ->getHumanName() ),
			],
			'key_opts'     => [],
			'href_options' => $this->getUrl_AdminPage()
		];

		if ( !$this->isEnabledForUiSummary() ) {
			$aThis[ 'key_opts' ][ 'mod' ] = $this->getModDisabledInsight();
		}
		else {
			$aThis[ 'key_opts' ][ 'mod' ] = [
				'name'    => __( 'Security Admin', 'wp-simple-firewall' ),
				'enabled' => $this->isEnabledForUiSummary(),
				'summary' => $this->isEnabledForUiSummary() ?
					__( 'Security plugin is protected against tampering', 'wp-simple-firewall' )
					: __( 'Security plugin is vulnerable to tampering', 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'admin_access_key' ),
			];

			$bWpOpts = $this->getAdminAccessArea_Options();
			$aThis[ 'key_opts' ][ 'wpopts' ] = [
				'name'    => __( 'Important Options', 'wp-simple-firewall' ),
				'enabled' => $bWpOpts,
				'summary' => $bWpOpts ?
					__( 'Important WP options are protected against tampering', 'wp-simple-firewall' )
					: __( "Important WP options aren't protected against tampering", 'wp-simple-firewall' ),
				'weight'  => 2,
				'href'    => $this->getUrl_DirectLinkToOption( 'admin_access_restrict_options' ),
			];

			$bUsers = $this->isAdminAccessAdminUsersEnabled();
			$aThis[ 'key_opts' ][ 'adminusers' ] = [
				'name'    => __( 'WP Admins', 'wp-simple-firewall' ),
				'enabled' => $bUsers,
				'summary' => $bUsers ?
					__( 'Admin users are protected against tampering', 'wp-simple-firewall' )
					: __( "Admin users aren't protected against tampering", 'wp-simple-firewall' ),
				'weight'  => 1,
				'href'    => $this->getUrl_DirectLinkToOption( 'admin_access_restrict_admin_users' ),
			];
		}

		$aAllData[ $this->getSlug() ] = $aThis;
		return $aAllData;
	}

	/**
	 * @param array $aAllNotices
	 * @return array
	 */
	public function addInsightsNoticeData( $aAllNotices ) {

		$aNotices = [
			'title'    => __( 'Security Admin Protection', 'wp-simple-firewall' ),
			'messages' => []
		];

		{//sec admin
			if ( !$this->isEnabledSecurityAdmin() ) {
				$aNotices[ 'messages' ][ 'sec_admin' ] = [
					'title'   => 'Security Plugin Unprotected',
					'message' => sprintf(
						__( "The Security Admin protection is not active.", 'wp-simple-firewall' ),
						$this->getCon()->getHumanName()
					),
					'href'    => $this->getUrl_AdminPage(),
					'action'  => sprintf( 'Go To %s', __( 'Options', 'wp-simple-firewall' ) ),
					'rec'     => __( 'Security Admin should be turned-on to protect your security settings.', 'wp-simple-firewall' )
				];
			}
		}

		$aNotices[ 'count' ] = count( $aNotices[ 'messages' ] );
		$aAllNotices[ 'sec_admin' ] = $aNotices;

		return $aAllNotices;
	}

	/**
	 * @return bool
	 */
	protected function isEnabledForUiSummary() {
		return parent::isEnabledForUiSummary() && $this->isEnabledSecurityAdmin();
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_SectionTitles( $aOptionsParams ) {

		$sSectionSlug = $aOptionsParams[ 'slug' ];
		$sPluginName = $this->getCon()->getHumanName();
		switch ( $sSectionSlug ) {

			case 'section_enable_plugin_feature_admin_access_restriction' :
				$sTitleShort = sprintf( __( '%s/%s ', 'wp-simple-firewall' ), __( 'On', 'wp-simple-firewall' ), __( 'Off', 'wp-simple-firewall' ) );
				$sTitle = sprintf( __( 'Enable Module: %s', 'wp-simple-firewall' ), $this->getMainFeatureName() );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restricts access to this plugin preventing unauthorized changes to your security settings.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), sprintf( __( 'Keep the %s feature turned on.', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) ) ),
					sprintf( __( 'You need to also enter a new Access Key to enable this feature.', 'wp-simple-firewall' ) ),
				];
				break;

			case 'section_admin_access_restriction_settings' :
				$sTitle = __( 'Security Admin Restriction Settings', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restricts access to this plugin preventing unauthorized changes to your security settings.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
				];
				$sTitleShort = __( 'Security Admin Settings', 'wp-simple-firewall' );
				break;

			case 'section_admin_access_restriction_areas' :
				$sTitle = __( 'Security Admin Restriction Zones', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s', __( 'Purpose', 'wp-simple-firewall' ), __( 'Restricts access to key WordPress areas for all users not authenticated with the Security Admin Access system.', 'wp-simple-firewall' ) ),
					sprintf( '%s - %s', __( 'Recommendation', 'wp-simple-firewall' ), __( 'Use of this feature is highly recommend.', 'wp-simple-firewall' ) ),
				];
				$sTitleShort = __( 'Access Restriction Zones', 'wp-simple-firewall' );
				break;

			case 'section_whitelabel' :
				$sTitle = __( 'White Label', 'wp-simple-firewall' );
				$aSummary = [
					sprintf( '%s - %s',
						__( 'Purpose', 'wp-simple-firewall' ),
						sprintf( __( 'Rename and re-brand the %s plugin for your client site installations.', 'wp-simple-firewall' ),
							$sPluginName )
					),
					sprintf( '%s - %s',
						__( 'Important', 'wp-simple-firewall' ),
						sprintf( __( 'The Security Admin system must be active for these settings to apply.', 'wp-simple-firewall' ),
							$sPluginName )
					)
				];
				$sTitleShort = __( 'White Label', 'wp-simple-firewall' );
				break;

			default:
				throw new \Exception( sprintf( 'A section slug was defined but with no associated strings. Slug: "%s".', $sSectionSlug ) );
		}
		$aOptionsParams[ 'title' ] = $sTitle;
		$aOptionsParams[ 'summary' ] = ( isset( $aSummary ) && is_array( $aSummary ) ) ? $aSummary : [];
		$aOptionsParams[ 'title_short' ] = $sTitleShort;
		return $aOptionsParams;
	}

	/**
	 * @param array $aOptionsParams
	 * @return array
	 * @throws \Exception
	 */
	protected function loadStrings_Options( $aOptionsParams ) {

		$sKey = $aOptionsParams[ 'key' ];
		$sPluginName = $this->getCon()->getHumanName();
		switch ( $sKey ) {

			case 'enable_admin_access_restriction' :
				$sName = sprintf( __( 'Enable %s Module', 'wp-simple-firewall' ), __( 'Security Admin', 'wp-simple-firewall' ) );
				$sSummary = __( 'Enforce Security Admin Access Restriction', 'wp-simple-firewall' );
				$sDescription = __( 'Enable this with great care and consideration. Ensure that you set a key that you have set an access key that you will remember.', 'wp-simple-firewall' );
				break;

			case 'admin_access_key' :
				$sName = __( 'Security Admin Access Key', 'wp-simple-firewall' );
				$sSummary = __( 'Provide/Update Security Admin Access Key', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'If you forget this, you could potentially lock yourself out from using this plugin.', 'wp-simple-firewall' ) )
								.'<br/><strong>'.( $this->hasAccessKey() ? __( 'Security Key Currently Set', 'wp-simple-firewall' ) : __( 'Security Key NOT Currently Set', 'wp-simple-firewall' ) ).'</strong>'
								.( $this->hasAccessKey() ? '<br/>'.sprintf( __( 'To delete the current security key, type exactly "%s" and save.', 'wp-simple-firewall' ), '<strong>DELETE</strong>' ) : '' );
				break;

			case 'sec_admin_users' :
				$sName = __( 'Security Admins', 'wp-simple-firewall' );
				$sSummary = __( 'Persistent Security Admins', 'wp-simple-firewall' );
				$sDescription = __( "Users provided will be security admins automatically, without needing the security key.", 'wp-simple-firewall' )
								.'<br/>'.__( 'Enter admin username, email or ID.', 'wp-simple-firewall' ).' '.__( '1 entry per-line.', 'wp-simple-firewall' )
								.'<br/>'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), __( 'Verified users will be converted to usernames.', 'wp-simple-firewall' ) );
				break;

			case 'admin_access_timeout' :
				$sName = __( 'Security Admin Timeout', 'wp-simple-firewall' );
				$sSummary = __( 'Specify An Automatic Timeout Interval For Security Admin Access', 'wp-simple-firewall' );
				$sDescription = __( 'This will automatically expire your Security Admin Session.', 'wp-simple-firewall' )
								.'<br />'
								.sprintf(
									'%s: %s',
									__( 'Default', 'wp-simple-firewall' ),
									sprintf( '%s minutes', $this->getOptionsVo()
																->getOptDefault( 'admin_access_timeout' ) )
								);
				break;

			case 'admin_access_restrict_posts' :
				$sName = __( 'Pages', 'wp-simple-firewall' );
				$sSummary = __( 'Restrict Access To Key WordPress Posts And Pages Actions', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict access to page/post creation, editing and deletion.', 'wp-simple-firewall' ) )
								.'<br />'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( 'Selecting "%s" will also restrict all other options.', 'wp-simple-firewall' ), __( 'Edit', 'wp-simple-firewall' ) ) );
				break;

			case 'admin_access_restrict_plugins' :
				$sName = __( 'Plugins', 'wp-simple-firewall' );
				$sSummary = __( 'Restrict Access To Key WordPress Plugin Actions', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict access to plugin installation, update, activation and deletion.', 'wp-simple-firewall' ) )
								.'<br />'.sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ), sprintf( __( 'Selecting "%s" will also restrict all other options.', 'wp-simple-firewall' ), __( 'Activate', 'wp-simple-firewall' ) ) );
				break;

			case 'admin_access_restrict_options' :
				$sName = __( 'WordPress Options', 'wp-simple-firewall' );
				$sSummary = __( 'Restrict Access To Certain WordPress Admin Options', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict the ability of WordPress administrators from changing key WordPress settings.', 'wp-simple-firewall' ) );
				break;

			case 'admin_access_restrict_admin_users' :
				$sName = __( 'Admin Users', 'wp-simple-firewall' );
				$sSummary = __( 'Restrict Access To Create/Delete/Modify Other Admin Users', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict the ability of WordPress administrators from creating, modifying or promoting other administrators.', 'wp-simple-firewall' ) );
				break;

			case 'admin_access_restrict_themes' :
				$sName = __( 'Themes', 'wp-simple-firewall' );
				$sSummary = __( 'Restrict Access To WordPress Theme Actions', 'wp-simple-firewall' );
				$sDescription = sprintf( '%s: %s', __( 'Careful', 'wp-simple-firewall' ), __( 'This will restrict access to theme installation, update, activation and deletion.', 'wp-simple-firewall' ) )
								.'<br />'.
								sprintf( '%s: %s',
									__( 'Note', 'wp-simple-firewall' ),
									sprintf(
										__( 'Selecting "%s" will also restrict all other options.', 'wp-simple-firewall' ),
										sprintf(
											__( '%s and %s', 'wp-simple-firewall' ),
											__( 'Activate', 'wp-simple-firewall' ),
											__( 'Edit Theme Options', 'wp-simple-firewall' )
										)
									)
								);
				break;

			case 'whitelabel_enable' :
				$sName = sprintf( '%s: %s', __( 'Enable', 'wp-simple-firewall' ), __( 'White Label', 'wp-simple-firewall' ) );
				$sSummary = __( 'Activate Your White Label Settings', 'wp-simple-firewall' );
				$sDescription = __( 'Turn on/off the application of your White Label settings.', 'wp-simple-firewall' );
				break;
			case 'wl_hide_updates' :
				$sName = __( 'Hide Updates', 'wp-simple-firewall' );
				$sSummary = __( 'Hide Plugin Updates From Non-Security Admins', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'Hide available %s updates from non-security administrators.', 'wp-simple-firewall' ), $sPluginName );
				break;
			case 'wl_pluginnamemain' :
				$sName = __( 'Plugin Name', 'wp-simple-firewall' );
				$sSummary = __( 'The Name Of The Plugin', 'wp-simple-firewall' );
				$sDescription = __( 'The name of the plugin that will be displayed to your site users.', 'wp-simple-firewall' );
				break;
			case 'wl_namemenu' :
				$sName = __( 'Menu Title', 'wp-simple-firewall' );
				$sSummary = __( 'The Main Menu Title Of The Plugin', 'wp-simple-firewall' );
				$sDescription = sprintf( __( 'The Main Menu Title Of The Plugin. If left empty, the "%s" will be used.', 'wp-simple-firewall' ), __( 'Plugin Name', 'wp-simple-firewall' ) );
				break;
			case 'wl_companyname' :
				$sName = __( 'Company Name', 'wp-simple-firewall' );
				$sSummary = __( 'The Name Of Your Company', 'wp-simple-firewall' );
				$sDescription = __( 'Provide the name of your company.', 'wp-simple-firewall' );
				break;
			case 'wl_description' :
				$sName = __( 'Description', 'wp-simple-firewall' );
				$sSummary = __( 'The Description Of The Plugin', 'wp-simple-firewall' );
				$sDescription = __( 'The description of the plugin displayed on the plugins page.', 'wp-simple-firewall' );
				break;
			case 'wl_homeurl' :
				$sName = __( 'Home URL', 'wp-simple-firewall' );
				$sSummary = __( 'Plugin Home Page URL', 'wp-simple-firewall' );
				$sDescription = __( "When a user clicks the home link for this plugin, this is where they'll be directed.", 'wp-simple-firewall' );
				break;
			case 'wl_menuiconurl' :
				$sName = __( 'Menu Icon', 'wp-simple-firewall' );
				$sSummary = __( 'Menu Icon URL', 'wp-simple-firewall' );
				$sDescription = __( 'The URL of the icon to display in the menu.', 'wp-simple-firewall' )
								.' '.sprintf( __( 'The %s should measure %s.', 'wp-simple-firewall' ), __( 'icon', 'wp-simple-firewall' ), '16px x 16px' );
				break;
			case 'wl_dashboardlogourl' :
				$sName = __( 'Dashboard Logo', 'wp-simple-firewall' );
				$sSummary = __( 'Dashboard Logo URL', 'wp-simple-firewall' );
				$sDescription = __( 'The URL of the logo to display in the admin pages.', 'wp-simple-firewall' )
								.' '.sprintf( __( 'The %s should measure %s.', 'wp-simple-firewall' ), __( 'logo', 'wp-simple-firewall' ), '128px x 128px' );
				break;
			case 'wl_login2fa_logourl' :
				$sName = __( '2FA Login Logo URL', 'wp-simple-firewall' );
				$sSummary = __( '2FA Login Logo URL', 'wp-simple-firewall' );
				$sDescription = __( 'The URL of the logo to display on the Two-Factor Authentication login page.', 'wp-simple-firewall' );
				break;

			default:
				throw new \Exception( sprintf( 'An option has been defined but without strings assigned to it. Option key: "%s".', $sKey ) );
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

		if ( $this->getAccessKeyHash() == self::HASH_DELETE ) {
			$this->clearAdminAccessKey()
				 ->setSecurityAdminStatusOnOff( false );
		}

		// Restricting Activate Plugins also means restricting the rest.
		$aPluginsRestrictions = $this->getAdminAccessArea_Plugins();
		if ( in_array( 'activate_plugins', $aPluginsRestrictions ) ) {
			$this->setOpt(
				'admin_access_restrict_plugins',
				array_unique( array_merge( $aPluginsRestrictions, [
					'install_plugins',
					'update_plugins',
					'delete_plugins'
				] ) )
			);
		}

		// Restricting Switch (Activate) Themes also means restricting the rest.
		$aThemesRestrictions = $this->getAdminAccessArea_Themes();
		if ( in_array( 'switch_themes', $aThemesRestrictions ) && in_array( 'edit_theme_options', $aThemesRestrictions ) ) {
			$this->setOpt(
				'admin_access_restrict_themes',
				array_unique( array_merge( $aThemesRestrictions, [
					'install_themes',
					'update_themes',
					'delete_themes'
				] ) )
			);
		}

		$aPostRestrictions = $this->getAdminAccessArea_Posts();
		if ( in_array( 'edit', $aPostRestrictions ) ) {
			$this->setOpt(
				'admin_access_restrict_posts',
				array_unique( array_merge( $aPostRestrictions, [ 'create', 'publish', 'delete' ] ) )
			);
		}
	}

	/**
	 */
	public function preDeactivatePlugin() {
		$oCon = $this->getCon();
		if ( !$oCon->isPluginAdmin() ) {
			Services::WpGeneral()->wpDie(
				__( "Sorry, this plugin is protected against unauthorised attempts to disable it.", 'wp-simple-firewall' )
				.'<br />'.sprintf( '<a href="%s">%s</a>',
					$this->getUrl_AdminPage(),
					__( "You'll just need to authenticate first and try again.", 'wp-simple-firewall' )
				)
			);
		}
	}
}