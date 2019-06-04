<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_AdminAccessRestriction extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	const HASH_DELETE = '32f68a60cef40faedbc6af20298c1a1e';

	/**
	 */
	protected function setupCustomHooks() {
		parent::setupCustomHooks();
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
	 * @return array
	 */
	public function getSecurityAdminUsers() {
		$aU = $this->getOpt( 'sec_admin_users', [] );
		return ( is_array( $aU ) && $this->isPremium() ) ? $aU : [];
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

	/**
	 * @return Shield\Modules\SecurityAdmin\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\SecurityAdmin\Options();
	}

	/**
	 * @return Shield\Modules\SecurityAdmin\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\SecurityAdmin\Strings();
	}

	/**
	 * @return string
	 * @deprecated
	 */
	protected function getAccessKeyHash() {
		return $this->getOpt( 'admin_access_key' );
	}

	/**
	 * @return bool
	 * @deprecated
	 */
	public function getAdminAccessArea_Options() {
		return $this->isOpt( 'admin_access_restrict_options', 'Y' );
	}

	/**
	 * @return array
	 * @deprecated
	 */
	public function getAdminAccessArea_Plugins() {
		return $this->getAdminAccessArea( 'plugins' );
	}

	/**
	 * @return array
	 * @deprecated
	 */
	public function getAdminAccessArea_Themes() {
		return $this->getAdminAccessArea( 'themes' );
	}

	/**
	 * @return array
	 * @deprecated
	 */
	public function getAdminAccessArea_Posts() {
		return $this->getAdminAccessArea( 'posts' );
	}

	/**
	 * @param string $sArea one of plugins, themes
	 * @return array
	 * @deprecated
	 */
	public function getAdminAccessArea( $sArea = 'plugins' ) {
		$aSettings = $this->getOpt( 'admin_access_restrict_'.$sArea, [] );
		return !is_array( $aSettings ) ? [] : $aSettings;
	}

	/**
	 * @return array
	 * @deprecated
	 */
	public function getRestrictedOptions() {
		$aOptions = $this->getDef( 'admin_access_options_to_restrict' );
		return is_array( $aOptions ) ? $aOptions : [];
	}

	/**
	 * TODO: Bug where if $sType is defined, it'll be set to 'wp' anyway
	 * @param string $sType - wp or wpms
	 * @return array
	 * @deprecated
	 */
	public function getOptionsToRestrict( $sType = '' ) {
		$sType = empty( $sType ) ? ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ) : 'wp';
		$aOptions = $this->getRestrictedOptions();
		return ( isset( $aOptions[ $sType.'_options' ] ) && is_array( $aOptions[ $sType.'_options' ] ) ) ? $aOptions[ $sType.'_options' ] : [];
	}

	/**
	 * @param string $sType - wp or wpms
	 * @return array
	 * @deprecated
	 */
	public function getOptionsPagesToRestrict( $sType = '' ) {
		$sType = empty( $sType ) ? ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ) : 'wp';
		$aOptions = $this->getRestrictedOptions();
		return ( isset( $aOptions[ $sType.'_pages' ] ) && is_array( $aOptions[ $sType.'_pages' ] ) ) ? $aOptions[ $sType.'_pages' ] : [];
	}
}