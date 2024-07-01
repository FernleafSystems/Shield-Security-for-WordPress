<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class SpamUserRegisterBlock extends Base {

	public function title() :string {
		return __( 'Block SPAM User Registrations', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Attempt to identify SPAM users and prevent their successful account creation.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return !empty( self::con()->comps->opts_lookup->getEmailValidateChecks() ) ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}