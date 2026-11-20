<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class PwnedPasswords extends Base {

	public function title() :string {
		return __( 'Block Pwned Passwords', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Prevent use of Pwned Passwords.', 'wp-simple-firewall' );
	}

	protected function configItem() :string {
		return 'pass_prevent_pwned';
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$opts = self::con()->opts;

		$status = parent::status();

		$pwnedOn = $opts->optIs( 'pass_prevent_pwned', 'Y' );
		$policiesOn = self::con()->comps->opts_lookup->isPassPoliciesEnabled();

		if ( $pwnedOn && $policiesOn ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			// Names policies being off as the cause, but stays on this setting: the warning on the
			// policies switch itself is said once, by the Password Policies component.
			if ( $policiesOn ) {
				$exp = __( 'Users are allowed to re-use compromised passwords.', 'wp-simple-firewall' );
			}
			elseif ( $pwnedOn ) {
				$exp = __( 'Users may re-use compromised passwords because password policies are turned off.', 'wp-simple-firewall' );
			}
			else {
				$exp = __( 'Users may re-use compromised passwords because password policies and pwned password blocking are both turned off.', 'wp-simple-firewall' );
			}
			$status[ 'exp' ][] = $this->explanationFixedBy( $exp, 'pass_prevent_pwned' );
		}

		return $status;
	}
}
