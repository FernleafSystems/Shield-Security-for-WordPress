<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_AdminAccessRestriction extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	const HASH_DELETE = '32f68a60cef40faedbc6af20298c1a1e';

	/**
	 * @var bool
	 */
	private $bValidSecAdminRequest;

	protected function setupCustomHooks() {
		parent::setupCustomHooks();
		add_action( $this->prefix( 'pre_deactivate_plugin' ), [ $this, 'preDeactivatePlugin' ] );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() {
		return $this->isEnabledSecurityAdmin() && parent::isReadyToExecute();
	}

	/**
	 * @return array
	 * @deprecated 9.2.0
	 */
	public function getSecurityAdminUsers() {
		$aU = $this->getOpt( 'sec_admin_users', [] );
		return ( is_array( $aU ) && $this->isPremium() ) ? $aU : [];
	}

	/**
	 * No checking of admin capabilities in-case of infinite loop with
	 * admin access caps check
	 * @return bool
	 */
	public function isRegisteredSecAdminUser() {
		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();
		$sUser = Services::WpUsers()->getCurrentWpUsername();
		return !empty( $sUser ) && in_array( $sUser, $opts->getSecurityAdminUsers() );
	}

	protected function preProcessOptions() {
		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();

		if ( $this->isValidSecAdminRequest() ) {
			$this->setSecurityAdminStatusOnOff( true );
		}

		// Verify whitelabel images
		if ( $this->isWlEnabled() ) {
			$aImages = [
				'wl_menuiconurl',
				'wl_dashboardlogourl',
				'wl_login2fa_logourl',
			];
			$oOpts = $this->getOptions();
			foreach ( $aImages as $sKey ) {
				if ( !Services::Data()->isValidWebUrl( $this->buildWlImageUrl( $sKey ) ) ) {
					$oOpts->resetOptToDefault( $sKey );
				}
			}
		}

		$this->setOpt( 'sec_admin_users', $this->verifySecAdminUsers( $opts->getSecurityAdminUsers() ) );
	}

	/**
	 * Ensures that all entries are valid users.
	 * @param string[] $aSecUsers
	 * @return string[]
	 */
	private function verifySecAdminUsers( $aSecUsers ) {
		$oDP = Services::Data();
		$oWpUsers = Services::WpUsers();
		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();

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
		if ( !empty( $aFiltered ) && !$opts->hasSecurityPIN() && !in_array( $oCurrent->user_login, $aFiltered ) ) {
			$aFiltered[] = $oCurrent->user_login;
		}

		natsort( $aFiltered );
		return array_unique( $aFiltered );
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
			elseif ( $nSecAdminAt > 0 ) {
				$nLeft = $this->getSecAdminTimeout() - ( Services::Request()->ts() - $nSecAdminAt );
			}
		}
		return max( 0, $nLeft );
	}

	/**
	 * @inheritDoc
	 */
	protected function handleModAction( $sAction ) {
		switch ( $sAction ) {
			case  'remove_secadmin_confirm':
				( new SecurityAdmin\Lib\Actions\RemoveSecAdmin() )
					->setMod( $this )
					->remove();
				break;
			default:
				break;
		}
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
		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();
		return $this->isModOptEnabled() &&
			   ( count( $opts->getSecurityAdminUsers() ) > 0 ||
				 ( $opts->hasSecurityPIN() && $this->getSecAdminTimeout() > 0 )
			   );
	}

	/**
	 * @param bool $bSetOn
	 * @return bool
	 */
	public function setSecurityAdminStatusOnOff( $bSetOn = false ) {
		/** @var Shield\Databases\Session\Update $oUpdater */
		$oUpdater = $this->getDbHandler_Sessions()->getQueryUpdater();
		return $bSetOn ?
			$oUpdater->startSecurityAdmin( $this->getSession() )
			: $oUpdater->terminateSecurityAdmin( $this->getSession() );
	}

	/**
	 * @return bool
	 */
	public function isValidSecAdminRequest() {
		return $this->isAccessKeyRequest() && $this->testSecAccessKeyRequest();
	}

	/**
	 * @return bool
	 */
	public function testSecAccessKeyRequest() {
		if ( !isset( $this->bValidSecAdminRequest ) ) {
			$bValid = false;
			$sReqKey = Services::Request()->post( 'sec_admin_key' );
			if ( !empty( $sReqKey ) ) {
				/** @var SecurityAdmin\Options $opts */
				$opts = $this->getOptions();
				$bValid = hash_equals( $opts->getSecurityPIN(), md5( $sReqKey ) );
				if ( !$bValid ) {
					$sEscaped = isset( $_POST[ 'sec_admin_key' ] ) ? $_POST[ 'sec_admin_key' ] : '';
					if ( !empty( $sEscaped ) ) {
						// Workaround for escaping of passwords
						$bValid = hash_equals( $opts->getSecurityPIN(), md5( $sEscaped ) );
						if ( $bValid ) {
							$this->setOpt( 'admin_access_key', md5( $sReqKey ) );
						}
					}
				}

				$this->getCon()->fireEvent( $bValid ? 'key_success' : 'key_fail' );
			}

			$this->bValidSecAdminRequest = $bValid;
		}
		return $this->bValidSecAdminRequest;
	}

	/**
	 * @return bool
	 */
	private function isAccessKeyRequest() {
		return strlen( Services::Request()->post( 'sec_admin_key', '' ) ) > 0;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public function verifyAccessKey( $key ) {
		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();
		return !empty( $key ) && hash_equals( $opts->getSecurityPIN(), md5( $key ) );
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
		$oOpts = $this->getOptions();

		$sLogoUrl = $this->getOpt( $sKey );
		if ( empty( $sLogoUrl ) ) {
			$oOpts->resetOptToDefault( $sKey );
			$sLogoUrl = $this->getOpt( $sKey );
		}
		if ( !empty( $sLogoUrl ) && !Services::Data()->isValidWebUrl( $sLogoUrl ) && strpos( $sLogoUrl, '/' ) !== 0 ) {
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
		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledWhitelabel() && $this->isPremium();
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
			throw new \Exception( 'Attempting to set an empty Security PIN.' );
		}
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( 'User does not have permission to update the Security PIN.' );
		}

		$this->setIsMainFeatureEnabled( true )
			 ->setOpt( 'admin_access_key', md5( $sKey ) );
		return $this->saveModOptions();
	}

	public function insertCustomJsVars_Admin() {
		parent::insertCustomJsVars_Admin();

		if ( $this->getSecAdminTimeLeft() > 0 ) {
			$aInsertData = [
				'ajax'         => [
					'check' => $this->getSecAdminCheckAjaxData(),
				],
				'is_sec_admin' => true, // if $nSecTimeLeft > 0
				'timeleft'     => $this->getSecAdminTimeLeft(), // JS uses milliseconds
				'strings'      => [
					'confirm' => __( 'Security Admin session has timed-out.', 'wp-simple-firewall' ).' '.__( 'Reload now?', 'wp-simple-firewall' ),
					'nearly'  => __( 'Security Admin session has nearly timed-out.', 'wp-simple-firewall' ),
					'expired' => __( 'Security Admin session has timed-out.', 'wp-simple-firewall' )
				]
			];
		}
		else {
			$aInsertData = [
				'ajax'    => [
					'req_email_remove' => $this->getAjaxActionData( 'req_email_remove' ),
				],
				'strings' => [
					'are_you_sure' => __( 'Are you sure?', 'wp-simple-firewall' )
				]
			];
		}

		if ( !empty( $aInsertData ) ) {
			wp_localize_script(
				$this->prefix( 'plugin' ),
				'icwp_wpsf_vars_secadmin',
				$aInsertData
			);
		}
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		/** @var SecurityAdmin\Options $opts */
		$opts = $this->getOptions();

		if ( hash_equals( $opts->getSecurityPIN(), self::HASH_DELETE ) ) {
			$opts->clearSecurityAdminKey();
			$this->setSecurityAdminStatusOnOff( false );
		}

		// Restricting Activate Plugins also means restricting the rest.
		$aPluginsRestrictions = $opts->getAdminAccessArea_Plugins();
		if ( in_array( 'activate_plugins', $aPluginsRestrictions ) ) {
			$opts->setOpt(
				'admin_access_restrict_plugins',
				array_unique( array_merge( $aPluginsRestrictions, [
					'install_plugins',
					'update_plugins',
					'delete_plugins'
				] ) )
			);
		}

		// Restricting Switch (Activate) Themes also means restricting the rest.
		$aThemesRestrictions = $opts->getAdminAccessArea_Themes();
		if ( in_array( 'switch_themes', $aThemesRestrictions ) && in_array( 'edit_theme_options', $aThemesRestrictions ) ) {
			$opts->setOpt(
				'admin_access_restrict_themes',
				array_unique( array_merge( $aThemesRestrictions, [
					'install_themes',
					'update_themes',
					'delete_themes'
				] ) )
			);
		}

		$aPostRestrictions = $opts->getAdminAccessArea_Posts();
		if ( in_array( 'edit', $aPostRestrictions ) ) {
			$opts->setOpt(
				'admin_access_restrict_posts',
				array_unique( array_merge( $aPostRestrictions, [ 'create', 'publish', 'delete' ] ) )
			);
		}
	}

	public function preDeactivatePlugin() {
		if ( !$this->getCon()->isPluginAdmin() ) {
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
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'SecurityAdmin';
	}
}