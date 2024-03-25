<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 19.1
 */
class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	/**
	 * @deprecated 19.1
	 */
	private function getRestrictedOptions() :array {
		$options = $this->getDef( 'options_to_restrict' );
		return \is_array( $options ) ? $options : [];
	}

	/**
	 * @deprecated 19.1
	 */
	public function getOptionsToRestrict() :array {
		$type = ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ).'_options';
		$options = $this->getRestrictedOptions();
		return \is_array( $options[ $type ] ?? [] ) ? $options[ $type ] : [];
	}

	/**
	 * @deprecated 19.1
	 */
	public function getOptionsPagesToRestrict() :array {
		$type = ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ).'_pages';
		$options = $this->getRestrictedOptions();
		return \is_array( $options[ $type ] ?? [] ) ? $options[ $type ] : [];
	}

	/**
	 * @deprecated 19.1
	 */
	public function getSecurityAdminUsers() :array {
		$users = $this->getOpt( 'sec_admin_users', [] );
		return ( \is_array( $users ) && self::con()->isPremiumActive() ) ? $users : [];
	}

	/**
	 * @deprecated 19.1
	 */
	public function getSecurityPIN() :string {
		return (string)$this->getOpt( 'admin_access_key', '' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function hasSecurityPIN() :bool {
		return !empty( $this->getOpt( 'admin_access_key', '' ) );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isSecAdminRestrictUsersEnabled() :bool {
		return $this->isOpt( 'admin_access_restrict_admin_users', 'Y' );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isRestrictWpOptions() :bool {
		return $this->isOpt( 'admin_access_restrict_options', 'Y' );
	}
}