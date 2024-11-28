<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class AutoIpBlocking extends Base {

	public function title() :string {
		return __( 'Automatic IP Blocking', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Monitor for malicious visitors and automatically block their IP addresses.', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Edit settings that control automatic IP blocking', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$lookup = self::con()->comps->opts_lookup;

		$status = parent::status();

		if ( $lookup->enabledIpAutoBlock() ) {
			if ( $lookup->getIpAutoBlockOffenseLimit() < 20 ) {
				$status[ 'level' ] = EnumEnabledStatus::GOOD;
			}
			else {
				$status[ 'level' ] = EnumEnabledStatus::OKAY;
				$status[ 'exp' ][] = __( 'The offense limit is quite high - you may want to consider decreasing it.', 'wp-simple-firewall' );
			}
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "Set a limit to offenses allowed before visitor IP is automatically blocked.", 'wp-simple-firewall' );
		}

		return $status;
	}
}