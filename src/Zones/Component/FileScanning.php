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

	public function postureSignals() :array {
		$con = self::con();
		$areas = $con->opts->optGet( 'file_scan_areas' );
		$repairAreas = $con->opts->optGet( 'file_repair_areas' );
		$signals = [
			$this->buildPostureSignal(
				'scan_enabled_mal',
				__( 'Malware Scanning', 'wp-simple-firewall' ),
				4,
				$con->comps->scans->AFS()->isEnabledMalwareScanPHP() ? 4 : 0,
				$con->comps->scans->AFS()->isEnabledMalwareScanPHP() ? 'good' : 'critical',
				$con->comps->scans->AFS()->isEnabledMalwareScanPHP(),
				[
					$con->comps->scans->AFS()->isEnabledMalwareScanPHP()
						? __( 'PHP files are scanned for malware.', 'wp-simple-firewall' )
						: __( 'PHP files are not scanned for malware.', 'wp-simple-firewall' ),
				]
			),
		];

		foreach ( [
			'wp'        => [ 'slug' => 'scan_enabled_afs_core', 'title' => __( 'Core File Scanning', 'wp-simple-firewall' ), 'weight' => 4 ],
			'plugins'   => [ 'slug' => 'scan_enabled_afs_plugins', 'title' => __( 'Plugin File Scanning', 'wp-simple-firewall' ), 'weight' => 4 ],
			'themes'    => [ 'slug' => 'scan_enabled_afs_themes', 'title' => __( 'Theme File Scanning', 'wp-simple-firewall' ), 'weight' => 4 ],
			'wpcontent' => [ 'slug' => 'scan_enabled_afs_wpcontent', 'title' => __( 'wp-content Scanning', 'wp-simple-firewall' ), 'weight' => 2 ],
			'wproot'    => [ 'slug' => 'scan_enabled_afs_wproot', 'title' => __( 'WP Root Scanning', 'wp-simple-firewall' ), 'weight' => 2 ],
		] as $areaKey => $definition ) {
			$enabled = \in_array( $areaKey, $areas, true );
			$signals[] = $this->buildPostureSignal(
				$definition[ 'slug' ],
				$definition[ 'title' ],
				$definition[ 'weight' ],
				$enabled ? $definition[ 'weight' ] : 0,
				$enabled ? 'good' : 'critical',
				$enabled,
				[
					$enabled
						? sprintf( __( '%s is enabled.', 'wp-simple-firewall' ), $definition[ 'title' ] )
						: sprintf( __( '%s is not enabled.', 'wp-simple-firewall' ), $definition[ 'title' ] ),
				]
			);
		}

		foreach ( [
			'wp'      => [ 'slug' => 'scan_enabled_afs_autorepair_core', 'title' => __( 'Core Auto-Repair', 'wp-simple-firewall' ), 'weight' => 6 ],
			'plugins' => [ 'slug' => 'scan_enabled_afs_autorepair_plugins', 'title' => __( 'Plugin Auto-Repair', 'wp-simple-firewall' ), 'weight' => 4 ],
			'themes'  => [ 'slug' => 'scan_enabled_afs_autorepair_themes', 'title' => __( 'Theme Auto-Repair', 'wp-simple-firewall' ), 'weight' => 2 ],
		] as $areaKey => $definition ) {
			$enabled = \in_array( $areaKey, $repairAreas, true );
			$signals[] = $this->buildPostureSignal(
				$definition[ 'slug' ],
				$definition[ 'title' ],
				$definition[ 'weight' ],
				$enabled ? $definition[ 'weight' ] : 0,
				$enabled ? 'good' : 'warning',
				$enabled,
				[
					$enabled
						? sprintf( __( '%s is enabled.', 'wp-simple-firewall' ), $definition[ 'title' ] )
						: sprintf( __( '%s is not enabled.', 'wp-simple-firewall' ), $definition[ 'title' ] ),
				]
			);
		}

		return $signals;
	}
}
