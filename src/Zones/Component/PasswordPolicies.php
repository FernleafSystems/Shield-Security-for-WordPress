<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class PasswordPolicies extends Base {

	public function title() :string {
		return __( 'Password Policies', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Restrict password parameters to ensure higher user account security.', 'wp-simple-firewall' );
	}

	protected function postureWeight() :int {
		return 3;
	}

	protected function status() :array {
		$status = parent::status();
		if ( self::con()->comps->opts_lookup->isPassPoliciesEnabled() ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
			$status[ 'exp' ][] = __( 'Password policies are enabled to help promote good password hygiene.', 'wp-simple-firewall' );
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "Password policies aren't enabled which may lead to poor password hygiene.", 'wp-simple-firewall' );
		}
		return $status;
	}
}
