<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class ScanScheduling extends Base {

	public function title() :string {
		return __( 'Scan Scheduling', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Run automatic scans frequently enough to catch problems early.', 'wp-simple-firewall' );
	}

	protected function postureWeight() :int {
		return 2;
	}

	protected function status() :array {
		$status = parent::status();
		$frequency = (int)self::con()->opts->optGet( 'scan_frequency' );
		if ( $frequency > 1 ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
			$status[ 'exp' ][] = __( 'Scans are scheduled to run at least twice per day.', 'wp-simple-firewall' );
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( 'Scans are only scheduled to run once per day.', 'wp-simple-firewall' );
		}
		return $status;
	}
}
