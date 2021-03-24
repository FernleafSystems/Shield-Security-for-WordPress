<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class ModCon extends BaseShield\ModCon {

	const HASH_DELETE = '32f68a60cef40faedbc6af20298c1a1e';

	/**
	 * @var bool
	 */
	private $bValidSecAdminRequest;

	/**
	 * @var Lib\WhiteLabel\ApplyLabels
	 */
	private $whitelabelCon;

	protected function setupCustomHooks() {
		add_action( $this->prefix( 'pre_deactivate_plugin' ), [ $this, 'preDeactivatePlugin' ] );
	}

	public function getWhiteLabelController() :Lib\WhiteLabel\ApplyLabels {
		if ( !$this->whitelabelCon instanceof Lib\WhiteLabel\ApplyLabels ) {
			$this->whitelabelCon = ( new Lib\WhiteLabel\ApplyLabels() )
				->setMod( $this );
		}
		return $this->whitelabelCon;
	}

	public function getSecAdminLoginAjaxData() :array {
		return $this->getAjaxActionData( 'sec_admin_login' );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return $this->isEnabledSecurityAdmin() && parent::isReadyToExecute();
	}

	/**
	 * No checking of admin capabilities in-case of infinite loop with
	 * admin access caps check
	 * @return bool
	 */
	public function isRegisteredSecAdminUser() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$sUser = Services::WpUsers()->getCurrentWpUsername();
		return !empty( $sUser ) && in_array( $sUser, $opts->getSecurityAdminUsers() );
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		if ( $this->isValidSecAdminRequest() ) {
			$this->setSecurityAdminStatusOnOff( true );
		}

		// Verify whitelabel images
		if ( $this->isWlEnabled() ) {
			foreach ( [ 'wl_menuiconurl', 'wl_dashboardlogourl', 'wl_login2fa_logourl' ] as $key ) {
				if ( !Services::Data()->isValidWebUrl( $this->buildWlImageUrl( $key ) ) ) {
					$opts->resetOptToDefault( $key );
				}
			}
		}

		$opts->setOpt( 'sec_admin_users', $this->verifySecAdminUsers( $opts->getSecurityAdminUsers() ) );

		if ( hash_equals( $opts->getSecurityPIN(), self::HASH_DELETE ) ) {
			$opts->clearSecurityAdminKey();
			$this->setSecurityAdminStatusOnOff( false );
			// If you delete the PIN, you also delete the sec admins. Prevents a lock out bug.
			$opts->setOpt( 'sec_admin_users', [] );
		}
	}

	/**
	 * Ensures that all entries are valid users.
	 * @param string[] $aSecUsers
	 * @return string[]
	 */
	private function verifySecAdminUsers( $aSecUsers ) {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$DP = Services::Data();
		$WPU = Services::WpUsers();

		$aFiltered = [];
		foreach ( $aSecUsers as $nCurrentKey => $usernameOrEmail ) {
			$user = null;

			if ( !empty( $usernameOrEmail ) ) {
				if ( $DP->validEmail( $usernameOrEmail ) ) {
					$user = $WPU->getUserByEmail( $usernameOrEmail );
				}
				else {
					$user = $WPU->getUserByUsername( $usernameOrEmail );
					if ( is_null( $user ) && is_numeric( $usernameOrEmail ) ) {
						$user = $WPU->getUserById( $usernameOrEmail );
					}
				}
			}

			if ( $user instanceof \WP_User && $user->ID > 0 && $WPU->isUserAdmin( $user ) ) {
				$aFiltered[] = $user->user_login;
			}
		}

		// We now run a bit of a sanity check to ensure that the current user is
		// not adding users here that aren't themselves without a key to still gain access
		$oCurrent = $WPU->getCurrentWpUser();
		if ( !empty( $aFiltered ) && !$opts->hasSecurityPIN() && !in_array( $oCurrent->user_login, $aFiltered ) ) {
			$aFiltered[] = $oCurrent->user_login;
		}

		natsort( $aFiltered );
		return array_unique( $aFiltered );
	}

	public function getSecAdminTimeout() :int {
		return (int)$this->getOptions()->getOpt( 'admin_access_timeout' )*MINUTE_IN_SECONDS;
	}

	/**
	 * Only returns greater than 0 if you have a valid Sec admin session
	 */
	public function getSecAdminTimeLeft() :int {
		$nLeft = 0;
		if ( $this->getCon()->getModule_Sessions()->getSessionCon()->hasSession() ) {

			$nSecAdminAt = $this->getSession()->getSecAdminAt();
			if ( $this->isRegisteredSecAdminUser() ) {
				$nLeft = 0;
			}
			elseif ( $nSecAdminAt > 0 ) {
				$nLeft = $this->getSecAdminTimeout() - ( Services::Request()->ts() - $nSecAdminAt );
			}
		}
		return (int)max( 0, $nLeft );
	}

	protected function handleModAction( string $action ) {
		switch ( $action ) {
			case  'remove_secadmin_confirm':
				( new Lib\Actions\RemoveSecAdmin() )
					->setMod( $this )
					->remove();
				break;
			default:
				parent::handleModAction( $action );
				break;
		}
	}

	public function isSecAdminSessionValid() :bool {
		return $this->getSecAdminTimeLeft() > 0;
	}

	public function isEnabledSecurityAdmin() :bool {
		/** @var Options $opts */
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

	public function isValidSecAdminRequest() :bool {
		return $this->isAccessKeyRequest() && $this->testSecAccessKeyRequest();
	}

	public function testSecAccessKeyRequest() :bool {
		if ( !isset( $this->bValidSecAdminRequest ) ) {
			$bValid = false;
			$sReqKey = Services::Request()->post( 'sec_admin_key' );
			if ( !empty( $sReqKey ) ) {
				/** @var Options $opts */
				$opts = $this->getOptions();
				$bValid = hash_equals( $opts->getSecurityPIN(), md5( $sReqKey ) );
				if ( !$bValid ) {
					$sEscaped = isset( $_POST[ 'sec_admin_key' ] ) ? $_POST[ 'sec_admin_key' ] : '';
					if ( !empty( $sEscaped ) ) {
						// Workaround for escaping of passwords
						$bValid = hash_equals( $opts->getSecurityPIN(), md5( $sEscaped ) );
						if ( $bValid ) {
							$opts->setOpt( 'admin_access_key', md5( $sReqKey ) );
						}
					}
				}

				$this->getCon()->fireEvent( $bValid ? 'key_success' : 'key_fail' );
			}

			$this->bValidSecAdminRequest = $bValid;
		}
		return $this->bValidSecAdminRequest;
	}

	private function isAccessKeyRequest() :bool {
		return strlen( Services::Request()->post( 'sec_admin_key', '' ) ) > 0;
	}

	public function verifyAccessKey( string $key ) :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return !empty( $key ) && hash_equals( $opts->getSecurityPIN(), md5( $key ) );
	}

	public function getWhitelabelOptions() :array {
		$opts = $this->getOptions();
		$main = $opts->getOpt( 'wl_pluginnamemain' );
		$menu = $opts->getOpt( 'wl_namemenu' );
		if ( empty( $menu ) ) {
			$menu = $main;
		}

		return [
			'name_main'            => $main,
			'name_menu'            => $menu,
			'name_company'         => $opts->getOpt( 'wl_companyname' ),
			'description'          => $opts->getOpt( 'wl_description' ),
			'url_home'             => $opts->getOpt( 'wl_homeurl' ),
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
	 * @param string $key
	 * @return string
	 */
	private function buildWlImageUrl( $key ) {
		$opts = $this->getOptions();

		$sLogoUrl = $opts->getOpt( $key );
		if ( empty( $sLogoUrl ) ) {
			$opts->resetOptToDefault( $key );
			$sLogoUrl = $opts->getOpt( $key );
		}
		if ( !empty( $sLogoUrl ) && !Services::Data()->isValidWebUrl( $sLogoUrl ) && strpos( $sLogoUrl, '/' ) !== 0 ) {
			$sLogoUrl = $this->getCon()->urls->forImage( $sLogoUrl );
			if ( empty( $sLogoUrl ) ) {
				$opts->resetOptToDefault( $key );
				$sLogoUrl = $this->getCon()->urls->forImage( $opts->getOpt( $key ) );
			}
		}

		return $sLogoUrl;
	}

	/**
	 * @deprecated 11.0
	 */
	public function isWlEnabled() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledWhitelabel() && $this->isPremium();
	}

	public function isWlHideUpdates() :bool {
		return $this->isEnabledWhitelabel() && $this->getOptions()->isOpt( 'wl_hide_updates', 'Y' );
	}

	/**
	 * @param string $pin
	 * @return $this
	 * @throws \Exception
	 */
	public function setNewPinManually( string $pin ) {
		if ( empty( $pin ) ) {
			throw new \Exception( 'Attempting to set an empty Security PIN.' );
		}
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( 'User does not have permission to update the Security PIN.' );
		}

		$this->setIsMainFeatureEnabled( true );
		$this->getOptions()->setOpt( 'admin_access_key', md5( $pin ) );
		return $this->saveModOptions();
	}

	public function getScriptLocalisations() :array {
		$locals = parent::getScriptLocalisations();

		if ( $this->getSecAdminTimeLeft() > 0 ) {
			$data = [
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
			$data = [
				'ajax'    => [
					'req_email_remove' => $this->getAjaxActionData( 'req_email_remove' ),
				],
				'strings' => [
					'are_you_sure' => __( 'Are you sure?', 'wp-simple-firewall' )
				]
			];
		}

		$locals[] = [
			'plugin',
			'icwp_wpsf_vars_secadmin',
			$data
		];

		return $locals;
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		/** @var Options $opts */
		$opts = $this->getOptions();

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
}