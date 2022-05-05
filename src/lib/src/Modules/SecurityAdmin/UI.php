<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class UI extends BaseShield\UI {

	public function getSectionWarnings( string $section ) :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$warning = [];

		switch ( $section ) {
			case 'section_whitelabel':
				if ( !$mod->getSecurityAdminController()->isEnabledSecAdmin() ) {
					$warning[] = __( 'Please also supply a Security Admin PIN, as whitelabel settings are only applied when the Security Admin feature is active.', 'wp-simple-firewall' );
				}
				break;
		}

		return $warning;
	}
}