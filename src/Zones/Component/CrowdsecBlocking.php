<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class CrowdsecBlocking extends Base {

	public function title() :string {
		return __( 'CrowdSec IP Blocking', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Crowd-Sourced IP address blocking in partnership with CrowdSec.', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Switch on/off CrowdSec IP block lists', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();

		if ( self::con()->comps->opts_lookup->enabledCrowdSecAutoBlock() ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "Switch-on the CrowdSec feature to block known (crowd-sourced) malicious IPs.", 'wp-simple-firewall' );
		}

		return $status;
	}
}