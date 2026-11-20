<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class PasswordStrength extends Base {

	public function title() :string {
		return __( 'Enforce Minimum Password Strength', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Enforce strong passwords for all users.', 'wp-simple-firewall' );
	}

	protected function configItem() :string {
		return 'pass_min_strength';
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$opts = self::con()->opts;

		$status = parent::status();

		$strengthOk = $opts->optGet( 'pass_min_strength' ) >= 3;
		$policiesOn = self::con()->comps->opts_lookup->isPassPoliciesEnabled();

		if ( $strengthOk && $policiesOn ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			// Names policies being off as the cause, but stays on this setting: the warning on the
			// policies switch itself is said once, by the Password Policies component.
			if ( $policiesOn ) {
				$exp = __( 'It is recommended to set the minimum password strength to Strong.', 'wp-simple-firewall' );
			}
			elseif ( $strengthOk ) {
				$exp = __( 'Users may set weak passwords because password policies are turned off.', 'wp-simple-firewall' );
			}
			else {
				$exp = __( 'Users may set weak passwords because password policies are turned off. It is also recommended to set the minimum password strength to Strong.', 'wp-simple-firewall' );
			}
			$status[ 'exp' ][] = $this->explanationFixedBy( $exp, 'pass_min_strength' );
		}

		return $status;
	}
}
