<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Options extends BaseShield\Options {

	public function clearSecurityAdminKey() :self {
		return $this->setOpt( 'admin_access_key', '' );
	}

	/**
	 * @deprecated 15.1
	 */
	public function getAdminAccessArea_Options() :bool {
		return $this->isOpt( 'admin_access_restrict_options', 'Y' );
	}

	/**
	 * @since      11.1
	 * @param string $area one of plugins, themes
	 * @deprecated 15.1
	 */
	public function getSecAdminAreaCaps( $area = 'plugins' ) :array {
		$d = $this->getOpt( 'admin_access_restrict_'.$area, [] );
		return is_array( $d ) ? $d : [];
	}

	private function getRestrictedOptions() :array {
		$options = $this->getDef( 'options_to_restrict' );
		return is_array( $options ) ? $options : [];
	}

	public function getOptionsToRestrict() :array {
		$type = ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ).'_options';
		$options = $this->getRestrictedOptions();
		return is_array( $options[ $type ] ?? [] ) ? $options[ $type ] : [];
	}

	public function getOptionsPagesToRestrict() :array {
		$type = ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ).'_pages';
		$options = $this->getRestrictedOptions();
		return is_array( $options[ $type ] ?? [] ) ? $options[ $type ] : [];
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

	public function isEnabledMU() :bool {
		return $this->isOpt( 'enable_mu', 'Y' );
	}

	public function isSecAdminRestrictUsersEnabled() :bool {
		return $this->isOpt( 'admin_access_restrict_admin_users', 'Y' );
	}

	public function isRestrictWpOptions() :bool {
		return $this->isOpt( 'admin_access_restrict_options', 'Y' );
	}
}