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

	protected function configureStatus() :array {
		$states = $this->fileScanningStates();
		$status = parent::status();

		if ( !$states[ 'afs_enabled' ] ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( 'Automatic file scanning is disabled.', 'wp-simple-firewall' );
		}
		if ( $states[ 'afs_enabled' ] && empty( \array_filter( \array_intersect_key(
			$states[ 'scan_controls' ],
			\array_filter(
				$this->scanControlDefinitions(),
				static fn( array $definition ) :bool => !empty( $definition[ 'is_primary' ] )
			)
		) ) ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( 'No primary WordPress file coverage is enabled.', 'wp-simple-firewall' );
		}

		foreach ( $this->scanControlDefinitions() as $areaKey => $definition ) {
			if ( empty( $states[ 'scan_controls' ][ $areaKey ] ) ) {
				$status[ 'exp' ][] = $definition[ 'disabled_message' ];
			}
		}
		foreach ( $this->repairControlDefinitions() as $areaKey => $definition ) {
			if ( empty( $states[ 'repair_controls' ][ $areaKey ] ) ) {
				$status[ 'exp' ][] = $definition[ 'disabled_message' ];
			}
		}

		if ( $status[ 'level' ] !== EnumEnabledStatus::BAD ) {
			$status[ 'level' ] = empty( $status[ 'exp' ] ) ? EnumEnabledStatus::GOOD : EnumEnabledStatus::OKAY;
		}

		return $status;
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$states = $this->fileScanningStates();
		$status = parent::status();

		$status[ 'level' ] = $states[ 'afs_enabled' ] ? EnumEnabledStatus::GOOD : EnumEnabledStatus::BAD;
		foreach ( $this->scanControlDefinitions() as $areaKey => $definition ) {
			if ( empty( $states[ 'scan_controls' ][ $areaKey ] ) ) {
				$status[ 'exp' ][] = $definition[ 'disabled_message' ];
			}
		}

		return $status;
	}

	public function postureSignals() :array {
		$states = $this->fileScanningStates();
		$signals = [];

		foreach ( $this->scanControlDefinitions() as $areaKey => $definition ) {
			$enabled = !empty( $states[ 'scan_controls' ][ $areaKey ] );
			$signals[] = $this->buildPostureSignal(
				$definition[ 'slug' ],
				$definition[ 'title' ],
				$definition[ 'weight' ],
				$enabled ? $definition[ 'weight' ] : 0,
				$enabled ? 'good' : $definition[ 'severity' ],
				$enabled,
				[
					$enabled ? $definition[ 'enabled_message' ] : $definition[ 'posture_disabled_message' ],
				]
			);
		}

		foreach ( $this->repairControlDefinitions() as $areaKey => $definition ) {
			$enabled = !empty( $states[ 'repair_controls' ][ $areaKey ] );
			$signals[] = $this->buildPostureSignal(
				$definition[ 'slug' ],
				$definition[ 'title' ],
				$definition[ 'weight' ],
				$enabled ? $definition[ 'weight' ] : 0,
				$enabled ? 'good' : 'warning',
				$enabled,
				[ $enabled ? $definition[ 'enabled_message' ] : $definition[ 'posture_disabled_message' ] ]
			);
		}

		return $signals;
	}

	/**
	 * @return array{
	 *   afs_enabled:bool,
	 *   scan_controls:array<string,bool>,
	 *   repair_controls:array<string,bool>
	 * }
	 */
	private function fileScanningStates() :array {
		$afs = self::con()->comps->scans->AFS();
		$scanAreas = $this->selectedScanAreas();
		$afsEnabled = $this->resolveBooleanMethod( $afs, 'isEnabled', false );

		$scanControls = [];
		foreach ( $this->scanControlDefinitions() as $areaKey => $definition ) {
			$scanControls[ $areaKey ] = $this->resolveBooleanMethod(
				$afs,
				$definition[ 'method' ],
				$afsEnabled && \in_array( $areaKey, $scanAreas, true )
			);
		}

		$repairControls = [];
		foreach ( $this->repairControlDefinitions() as $areaKey => $definition ) {
			$repairControls[ $areaKey ] = $this->resolveBooleanMethod(
				$afs,
				$definition[ 'method' ],
				!empty( $scanControls[ $definition[ 'scan_area' ] ] )
				&& \in_array( $areaKey, $this->selectedRepairAreas(), true )
			);
		}

		return [
			'afs_enabled'      => $afsEnabled,
			'scan_controls'    => $scanControls,
			'repair_controls'  => $repairControls,
		];
	}

	/**
	 * @return array<string,array{
	 *   slug:string,
	 *   title:string,
	 *   weight:int,
	 *   severity:string,
	 *   is_primary:bool,
	 *   method:string,
	 *   disabled_message:string,
	 *   posture_disabled_message:string,
	 *   enabled_message:string
	 * }>
	 */
	private function scanControlDefinitions() :array {
		return [
			'malware_php' => [
				'slug'                    => 'scan_enabled_mal',
				'title'                   => __( 'Malware Scanning', 'wp-simple-firewall' ),
				'weight'                  => 4,
				'severity'                => 'critical',
				'is_primary'              => true,
				'method'                  => 'isEnabledMalwareScanPHP',
				'disabled_message'        => __( "PHP files aren't scanned for malware.", 'wp-simple-firewall' ),
				'posture_disabled_message'=> __( 'PHP files are not scanned for malware.', 'wp-simple-firewall' ),
				'enabled_message'         => __( 'PHP files are scanned for malware.', 'wp-simple-firewall' ),
			],
			'wp' => [
				'slug'                    => 'scan_enabled_afs_core',
				'title'                   => __( 'Core File Scanning', 'wp-simple-firewall' ),
				'weight'                  => 4,
				'severity'                => 'critical',
				'is_primary'              => true,
				'method'                  => 'isScanEnabledWpCore',
				'disabled_message'        => __( "WordPress core files aren't scanned for corruption.", 'wp-simple-firewall' ),
				'posture_disabled_message'=> __( 'WordPress core files are not scanned for corruption.', 'wp-simple-firewall' ),
				'enabled_message'         => __( 'WordPress core files are scanned for corruption.', 'wp-simple-firewall' ),
			],
			'plugins' => [
				'slug'                    => 'scan_enabled_afs_plugins',
				'title'                   => __( 'Plugin File Scanning', 'wp-simple-firewall' ),
				'weight'                  => 4,
				'severity'                => 'critical',
				'is_primary'              => true,
				'method'                  => 'isScanEnabledPlugins',
				'disabled_message'        => __( "Plugin files aren't scanned for corruption.", 'wp-simple-firewall' ),
				'posture_disabled_message'=> __( 'Plugin files are not scanned for corruption.', 'wp-simple-firewall' ),
				'enabled_message'         => __( 'Plugin files are scanned for corruption.', 'wp-simple-firewall' ),
			],
			'themes' => [
				'slug'                    => 'scan_enabled_afs_themes',
				'title'                   => __( 'Theme File Scanning', 'wp-simple-firewall' ),
				'weight'                  => 4,
				'severity'                => 'critical',
				'is_primary'              => true,
				'method'                  => 'isScanEnabledThemes',
				'disabled_message'        => __( "Theme files aren't scanned for corruption.", 'wp-simple-firewall' ),
				'posture_disabled_message'=> __( 'Theme files are not scanned for corruption.', 'wp-simple-firewall' ),
				'enabled_message'         => __( 'Theme files are scanned for corruption.', 'wp-simple-firewall' ),
			],
			'wpcontent' => [
				'slug'                    => 'scan_enabled_afs_wpcontent',
				'title'                   => __( 'wp-content Scanning', 'wp-simple-firewall' ),
				'weight'                  => 2,
				'severity'                => 'critical',
				'is_primary'              => false,
				'method'                  => 'isScanEnabledWpContent',
				'disabled_message'        => __( "/wp-content/ directory isn't scanned.", 'wp-simple-firewall' ),
				'posture_disabled_message'=> __( '/wp-content/ directory is not scanned.', 'wp-simple-firewall' ),
				'enabled_message'         => __( '/wp-content/ directory is scanned.', 'wp-simple-firewall' ),
			],
			'wproot' => [
				'slug'                    => 'scan_enabled_afs_wproot',
				'title'                   => __( 'WP Root Scanning', 'wp-simple-firewall' ),
				'weight'                  => 2,
				'severity'                => 'critical',
				'is_primary'              => false,
				'method'                  => 'isScanEnabledWpRoot',
				'disabled_message'        => __( "WP root directory isn't scanned.", 'wp-simple-firewall' ),
				'posture_disabled_message'=> __( 'WP root directory is not scanned.', 'wp-simple-firewall' ),
				'enabled_message'         => __( 'WP root directory is scanned.', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return array<string,array{
	 *   slug:string,
	 *   title:string,
	 *   weight:int,
	 *   severity:string,
	 *   scan_area:string,
	 *   method:string,
	 *   disabled_message:string,
	 *   posture_disabled_message:string,
	 *   enabled_message:string
	 * }>
	 */
	private function repairControlDefinitions() :array {
		return [
			'wp' => [
				'slug'                    => 'scan_enabled_afs_autorepair_core',
				'title'                   => __( 'Core Auto-Repair', 'wp-simple-firewall' ),
				'weight'                  => 6,
				'severity'                => 'warning',
				'scan_area'               => 'wp',
				'method'                  => 'isRepairFileWP',
				'disabled_message'        => __( "WordPress core file auto-repair isn't enabled.", 'wp-simple-firewall' ),
				'posture_disabled_message'=> __( 'WordPress core file auto-repair is not enabled.', 'wp-simple-firewall' ),
				'enabled_message'         => __( 'WordPress core file auto-repair is enabled.', 'wp-simple-firewall' ),
			],
			'plugin' => [
				'slug'                    => 'scan_enabled_afs_autorepair_plugins',
				'title'                   => __( 'Plugin Auto-Repair', 'wp-simple-firewall' ),
				'weight'                  => 4,
				'severity'                => 'warning',
				'scan_area'               => 'plugins',
				'method'                  => 'isRepairFilePlugin',
				'disabled_message'        => __( "Plugin file auto-repair isn't enabled.", 'wp-simple-firewall' ),
				'posture_disabled_message'=> __( 'Plugin file auto-repair is not enabled.', 'wp-simple-firewall' ),
				'enabled_message'         => __( 'Plugin file auto-repair is enabled.', 'wp-simple-firewall' ),
			],
			'theme' => [
				'slug'                    => 'scan_enabled_afs_autorepair_themes',
				'title'                   => __( 'Theme Auto-Repair', 'wp-simple-firewall' ),
				'weight'                  => 2,
				'severity'                => 'warning',
				'scan_area'               => 'themes',
				'method'                  => 'isRepairFileTheme',
				'disabled_message'        => __( "Theme file auto-repair isn't enabled.", 'wp-simple-firewall' ),
				'posture_disabled_message'=> __( 'Theme file auto-repair is not enabled.', 'wp-simple-firewall' ),
				'enabled_message'         => __( 'Theme file auto-repair is enabled.', 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return list<string>
	 */
	private function selectedScanAreas() :array {
		$areas = self::con()->opts->optGet( 'file_scan_areas' );
		return \is_array( $areas ) ? \array_values( \array_filter( $areas, 'is_string' ) ) : [];
	}

	/**
	 * @return list<string>
	 */
	private function selectedRepairAreas() :array {
		$areas = self::con()->opts->optGet( 'file_repair_areas' );
		return \is_array( $areas ) ? \array_values( \array_filter( $areas, 'is_string' ) ) : [];
	}

}
