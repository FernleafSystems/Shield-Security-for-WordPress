<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class SecadminEnabled extends Base {

	public function title() :string {
		return __( 'Security Admin Protection', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return sprintf( __( 'The Security Admin system protects WordPress and the %s plugin against tampering.', 'wp-simple-firewall' ), self::con()->labels->Name );
	}

	protected function tooltip() :string {
		return __( 'Provide a secret PIN to restrict admin access to your security settings', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( self::con()->comps->sec_admin->isEnabledSecAdmin() ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "A PIN needs to be set to enable the Security Admin.", 'wp-simple-firewall' );
		}

		return $status;
	}
}