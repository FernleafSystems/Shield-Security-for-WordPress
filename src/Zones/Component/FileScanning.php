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

	protected function tooltip() :string {
		return __( 'Edit automatic file scanning settings', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$con = self::con();
		$status = parent::status();

		$status[ 'level' ] = $con->comps->scans->AFS()->isEnabled() ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
		$areas = $con->opts->optGet( 'file_scan_areas' );
		if ( !\in_array( 'malware_php', $areas ) ) {
			$status[ 'exp' ][] = __( "PHP files aren't scanned for malware.", 'wp-simple-firewall' );
		}
		if ( !\in_array( 'plugins', $areas ) ) {
			$status[ 'exp' ][] = __( "Plugin files aren't scanned for corruption.", 'wp-simple-firewall' );
		}
		if ( !\in_array( 'themes', $areas ) ) {
			$status[ 'exp' ][] = __( "Theme files aren't scanned for corruption.", 'wp-simple-firewall' );
		}
		if ( !\in_array( 'wpcontent', $areas ) ) {
			$status[ 'exp' ][] = __( "/wp-content/ directory isn't scanned.", 'wp-simple-firewall' );
		}
		if ( !\in_array( 'wproot', $areas ) ) {
			$status[ 'exp' ][] = __( "WP root directory isn't scanned.", 'wp-simple-firewall' );
		}

		return $status;
	}
}