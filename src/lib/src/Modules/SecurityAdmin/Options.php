<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Options extends BaseShield\Options {

	public function clearSecurityAdminKey() :self {
		return $this->setOpt( 'admin_access_key', '' );
	}

	public function getAdminAccessArea_Options() :bool {
		return $this->isOpt( 'admin_access_restrict_options', 'Y' );
	}

	public function getAdminAccessArea_Plugins() :array {
		return $this->getAdminAccessArea( 'plugins' );
	}

	public function getAdminAccessArea_Themes() :array {
		return $this->getAdminAccessArea( 'themes' );
	}

	public function getAdminAccessArea_Posts() :array {
		return $this->getAdminAccessArea( 'posts' );
	}

	/**
	 * @param string $area one of plugins, themes
	 * @return array
	 * @since 11.1
	 */
	public function getSecAdminAreaCaps( $area = 'plugins' ) :array {
		$d = $this->getOpt( 'admin_access_restrict_'.$area, [] );
		return is_array( $d ) ? $d : [];
	}

	/**
	 * @param string $sArea one of plugins, themes
	 * @return array
	 * @deprecated 11.1
	 */
	private function getAdminAccessArea( $sArea = 'plugins' ) :array {
		$d = $this->getOpt( 'admin_access_restrict_'.$sArea, [] );
		return is_array( $d ) ? $d : [];
	}

	private function getRestrictedOptions() :array {
		$options = $this->getDef( 'options_to_restrict' );
		return is_array( $options ) ? $options : [];
	}

	/**
	 * TODO: Bug where if $sType is defined, it'll be set to 'wp' anyway
	 * @param string $type - wp or wpms
	 * @return array
	 */
	public function getOptionsToRestrict( $type = '' ) {
		$type = empty( $type ) ? ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ) : 'wp';
		$options = $this->getRestrictedOptions();
		return ( isset( $options[ $type.'_options' ] ) && is_array( $options[ $type.'_options' ] ) ) ? $options[ $type.'_options' ] : [];
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

	public function getSecurityAdminUsers() :array {
		$users = $this->getOpt( 'sec_admin_users', [] );
		return ( is_array( $users ) && $this->isPremium() ) ? $users : [];
	}

	public function getSecurityPIN() :string {
		return (string)$this->getOpt( 'admin_access_key', '' );
	}

	public function hasSecurityPIN() :bool {
		return strlen( $this->getSecurityPIN() ) == 32;
	}

	public function isEmailOverridePermitted() :bool {
		return $this->isOpt( 'allow_email_override', 'Y' );
	}

	public function isSecAdminRestrictUsersEnabled() :bool {
		return $this->isOpt( 'admin_access_restrict_admin_users', 'Y' );
	}
}