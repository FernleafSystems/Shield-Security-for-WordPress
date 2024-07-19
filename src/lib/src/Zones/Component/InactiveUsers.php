<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class InactiveUsers extends Base {

	public function title() :string {
		return __( 'Auto-Suspend Inactive Users', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Disable account access for inactive users.', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( self::con()->comps->user_suspend->isSuspendAutoIdleEnabled() ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "User accounts that become inactive over time may still allow access if the account is compromised.", 'wp-simple-firewall' );
		}

		return $status;
	}
}