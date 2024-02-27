<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Services\Services;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

	private function getRestrictedOptions() :array {
		$options = $this->getDef( 'options_to_restrict' );
		return \is_array( $options ) ? $options : [];
	}

	public function getOptionsToRestrict() :array {
		$type = ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ).'_options';
		$options = $this->getRestrictedOptions();
		return \is_array( $options[ $type ] ?? [] ) ? $options[ $type ] : [];
	}

	public function getOptionsPagesToRestrict() :array {
		$type = ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ).'_pages';
		$options = $this->getRestrictedOptions();
		return \is_array( $options[ $type ] ?? [] ) ? $options[ $type ] : [];
	}

	public function getSecurityAdminUsers() :array {
		$users = $this->getOpt( 'sec_admin_users', [] );
		return ( \is_array( $users ) && self::con()->isPremiumActive() ) ? $users : [];
	}

	public function getSecurityPIN() :string {
		return (string)$this->getOpt( 'admin_access_key', '' );
	}

	public function hasSecurityPIN() :bool {
		return !empty( $this->getSecurityPIN() );
	}

	public function isSecAdminRestrictUsersEnabled() :bool {
		return $this->isOpt( 'admin_access_restrict_admin_users', 'Y' );
	}

	public function isRestrictWpOptions() :bool {
		return $this->isOpt( 'admin_access_restrict_options', 'Y' );
	}
}