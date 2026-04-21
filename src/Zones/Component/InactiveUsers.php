<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class InactiveUsers extends Base {

	public function title() :string {
		return __( 'User Suspension', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Manually or automatically suspend user accounts to prevent login.', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Edit settings on user suspension', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$con = self::con();
		$status = parent::status();
		$userSuspend = $con->comps->user_suspend;
		$optsLookup = $con->comps->opts_lookup;

		$hasManualSuspend = $userSuspend->isSuspendManualEnabled();
		$hasAutoIdleSuspend = $userSuspend->isSuspendAutoIdleEnabled();
		$hasAutoPasswordSuspend = $userSuspend->isSuspendAutoPasswordEnabled();
		$autoPasswordEnabled = $con->opts->optIs( 'auto_password', 'Y' );

		if ( $hasAutoIdleSuspend || $hasAutoPasswordSuspend ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
			$status[ 'exp' ][] = $hasManualSuspend
				? __( 'Only manual user suspension is available; users are not suspended automatically.', 'wp-simple-firewall' )
				: __( 'No effective automatic user suspension rule is configured.', 'wp-simple-firewall' );
		}

		if ( $autoPasswordEnabled && !$hasAutoPasswordSuspend ) {
			if ( !$optsLookup->isPassPoliciesEnabled() ) {
				$status[ 'exp' ][] = __( 'Expired-password suspension is enabled but password policies are turned off.', 'wp-simple-firewall' );
			}
			if ( $optsLookup->getPassExpireTimeout() <= 0 ) {
				$status[ 'exp' ][] = __( 'Expired-password suspension is enabled but password expiration is not configured.', 'wp-simple-firewall' );
			}
		}

		return $status;
	}

	protected function postureWeight() :int {
		return 2;
	}
}
