<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class FileScanning extends Base {

	public function title() :string {
		return __( 'WordPress File Scanning', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Regularly scan the WordPress filesystem for infections, suspicious code, or unexpected changes.', 'wp-simple-firewall' );
	}

	public function enabledStatus() :string {
		return self::con()->comps->scans->AFS()->isEnabled() ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
	}
}