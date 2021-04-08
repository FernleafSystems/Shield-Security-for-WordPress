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

	/**
	 * @var Lib\SecurityAdmin\SecurityAdminController
	 */
	private $securityAdminCon;

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

	public function getSecurityAdminController() :Lib\SecurityAdmin\SecurityAdminController {
		if ( !$this->securityAdminCon instanceof Lib\SecurityAdmin\SecurityAdminController ) {
			$this->securityAdminCon = ( new Lib\SecurityAdmin\SecurityAdminController() )
				->setMod( $this );
		}
		return $this->securityAdminCon;
	}

	public function getSecAdminLoginAjaxData() :array {
		return $this->getAjaxActionData( 'sec_admin_login' );
	}

	protected function preProcessOptions() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		// Verify whitelabel images
		if ( $this->isWlEnabled() ) {
			foreach ( [ 'wl_menuiconurl', 'wl_dashboardlogourl', 'wl_login2fa_logourl' ] as $key ) {
				if ( !Services::Data()->isValidWebUrl( $this->buildWlImageUrl( $key ) ) ) {
					$opts->resetOptToDefault( $key );
				}
			}
		}

		$opts->setOpt( 'sec_admin_users',
			( new Lib\SecurityAdmin\VerifySecurityAdminList() )
				->setMod( $this )
				->run( $opts->getSecurityAdminUsers() )
		);

		if ( hash_equals( $opts->getSecurityPIN(), self::HASH_DELETE ) ) {
			$opts->clearSecurityAdminKey();
			( new Lib\SecurityAdmin\Ops\ToggleSecAdminStatus() )
				->setMod( $this )
				->turnOff();
			// If you delete the PIN, you also delete the sec admins. Prevents a lock out bug.
			$opts->setOpt( 'sec_admin_users', [] );
		}
	}

	protected function handleModAction( string $action ) {
		switch ( $action ) {
			case  'remove_secadmin_confirm':
				( new Lib\SecurityAdmin\Ops\RemoveSecAdmin() )
					->setMod( $this )
					->remove();
				break;
			default:
				parent::handleModAction( $action );
				break;
		}
	}

	/**
	 * @return array
	 * @deprecated 11.1
	 */
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
	 * Used by Wizard. TODO: sort out the wizard requests!
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
		( new Lib\SecurityAdmin\Ops\ToggleSecAdminStatus() )
			->setMod( $this )
			->turnOn();

		return $this->saveModOptions();
	}

	/**
	 * This is the point where you would want to do any options verification
	 */
	protected function doPrePluginOptionsSave() {
		/** @var Options $opts */
		$opts = $this->getOptions();

		// Restricting Activate Plugins also means restricting the rest.
		$pluginsRestrictions = $opts->getAdminAccessArea_Plugins();
		if ( in_array( 'activate_plugins', $pluginsRestrictions ) ) {
			$opts->setOpt(
				'admin_access_restrict_plugins',
				array_unique( array_merge( $pluginsRestrictions, [
					'install_plugins',
					'update_plugins',
					'delete_plugins'
				] ) )
			);
		}

		// Restricting Switch (Activate) Themes also means restricting the rest.
		$themesRestrictions = $opts->getAdminAccessArea_Themes();
		if ( in_array( 'switch_themes', $themesRestrictions ) && in_array( 'edit_theme_options', $themesRestrictions ) ) {
			$opts->setOpt(
				'admin_access_restrict_themes',
				array_unique( array_merge( $themesRestrictions, [
					'install_themes',
					'update_themes',
					'delete_themes'
				] ) )
			);
		}

		$postRestrictions = $opts->getAdminAccessArea_Posts();
		if ( in_array( 'edit', $postRestrictions ) ) {
			$opts->setOpt(
				'admin_access_restrict_posts',
				array_unique( array_merge( $postRestrictions, [ 'create', 'publish', 'delete' ] ) )
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
	 * No checking of admin capabilities in-case of infinite loop with
	 * admin access caps check
	 * @return bool
	 * @deprecated 11.1
	 */
	public function isRegisteredSecAdminUser() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$sUser = Services::WpUsers()->getCurrentWpUsername();
		return !empty( $sUser ) && in_array( $sUser, $opts->getSecurityAdminUsers() );
	}

	/**
	 * @return bool
	 * @deprecated 11.1
	 */
	public function isEnabledSecurityAdmin() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $this->isModOptEnabled() &&
			   ( count( $opts->getSecurityAdminUsers() ) > 0 ||
				 ( $opts->hasSecurityPIN() && $this->getSecAdminTimeout() > 0 )
			   );
	}

	/**
	 * @return bool
	 * @deprecated 11.1
	 */
	public function isSecAdminSessionValid() :bool {
		return $this->getSecAdminTimeLeft() > 0;
	}

	/**
	 * Only returns greater than 0 if you have a valid Sec admin session
	 * @deprecated 11.1
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

	/**
	 * @return int
	 * @deprecated 11.1
	 */
	public function getSecAdminTimeout() :int {
		return (int)$this->getOptions()->getOpt( 'admin_access_timeout' )*MINUTE_IN_SECONDS;
	}

	/**
	 * Ensures that all entries are valid users.
	 * @param string[] $aSecUsers
	 * @return string[]
	 * @deprecated 11.1
	 */
	private function verifySecAdminUsers( $aSecUsers ) {
		return $aSecUsers;
	}

	/**
	 * @return bool
	 * @deprecated 11.1
	 */
	private function isAccessKeyRequest() :bool {
		return strlen( Services::Request()->post( 'sec_admin_key', '' ) ) > 0;
	}

	public function verifyAccessKey( string $key ) :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return !empty( $key ) && hash_equals( $opts->getSecurityPIN(), md5( $key ) );
	}

	/**
	 * @return bool
	 * @deprecated 11.1
	 */
	public function testSecAccessKeyRequest() :bool {
		return ( new Lib\SecurityAdmin\Ops\VerifyPinRequest() )
			->setMod( $this )
			->run();
	}

	/**
	 * @return bool
	 * @deprecated 11.1
	 */
	public function isValidSecAdminRequest() :bool {
		return false;
	}

	/**
	 * @param bool $bSetOn
	 * @return bool
	 * @deprecated 11.1
	 */
	public function setSecurityAdminStatusOnOff( $bSetOn = false ) {
		return false;
	}
}