<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Options extends Base\ShieldOptions {

	/**
	 * @return $this
	 */
	public function clearSecurityAdminKey() {
		return $this->setOpt( 'admin_access_key', '' );
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
	private function getAdminAccessArea( $sArea = 'plugins' ) {
		$aD = $this->getOpt( 'admin_access_restrict_'.$sArea, [] );
		return is_array( $aD ) ? $aD : [];
	}

	/**
	 * @return array
	 */
	private function getRestrictedOptions() {
		$aOptions = $this->getDef( 'options_to_restrict' );
		return is_array( $aOptions ) ? $aOptions : [];
	}

	/**
	 * TODO: Bug where if $sType is defined, it'll be set to 'wp' anyway
	 * @param string $type - wp or wpms
	 * @return array
	 */
	public function getOptionsToRestrict( $type = '' ) {
		$type = empty( $type ) ? ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ) : 'wp';
		$aOptions = $this->getRestrictedOptions();
		return ( isset( $aOptions[ $type.'_options' ] ) && is_array( $aOptions[ $type.'_options' ] ) ) ? $aOptions[ $type.'_options' ] : [];
	}

	/**
	 * @param string $type - wp or wpms
	 * @return array
	 */
	public function getOptionsPagesToRestrict( $type = '' ) {
		$type = empty( $type ) ? ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ) : 'wp';
		$aOptions = $this->getRestrictedOptions();
		return ( isset( $aOptions[ $type.'_pages' ] ) && is_array( $aOptions[ $type.'_pages' ] ) ) ? $aOptions[ $type.'_pages' ] : [];
	}

	/**
	 * @return array
	 */
	public function getSecurityAdminUsers() {
		$users = $this->getOpt( 'sec_admin_users', [] );
		return ( is_array( $users ) && $this->isPremium() ) ? $users : [];
	}

	/**
	 * @return string
	 */
	public function getSecurityPIN() {
		return (string)$this->getOpt( 'admin_access_key', '' );
	}

	/**
	 * @return bool
	 */
	public function hasSecurityPIN() {
		return strlen( $this->getSecurityPIN() ) == 32;
	}

	/**
	 * @return bool
	 */
	public function isEnabledWhitelabel() {
		return $this->isOpt( 'whitelabel_enable', 'Y' ) && $this->isPremium();
	}

	/**
	 * @return bool
	 */
	public function isEmailOverridePermitted() {
		return $this->isOpt( 'allow_email_override', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isSecAdminRestrictUsersEnabled() {
		return $this->isOpt( 'admin_access_restrict_admin_users', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isWlHideUpdates() {
		return $this->isEnabledWhitelabel() && $this->isOpt( 'wl_hide_updates', 'Y' );
	}

	/**
	 * @return bool
	 * @deprecated 9.2.0
	 */
	public function hasAccessKey() {
		return $this->hasSecurityPIN();
	}

	/**
	 * @return string
	 * @deprecated 9.2.0
	 */
	public function getAccessKeyHash() {
		return $this->getSecurityPIN();
	}
}