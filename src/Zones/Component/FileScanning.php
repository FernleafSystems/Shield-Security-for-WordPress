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
			$status[ 'exp' ][] = $this->explanationFixedBy( __( 'No primary WordPress file coverage is enabled.', 'wp-simple-firewall' ), 'file_scan_areas' );
		}

		foreach ( $this->scanControlDefinitions() as $areaKey => $definition ) {
			if ( empty( $states[ 'scan_controls' ][ $areaKey ] ) ) {
				$status[ 'exp' ][] = $this->explanationFixedBy( $definition[ 'disabled_message' ], 'file_scan_areas' );
			}
		}
		foreach ( $this->repairControlDefinitions() as $areaKey => $definition ) {
			if ( empty( $states[ 'repair_controls' ][ $areaKey ] ) ) {
				$status[ 'exp' ][] = $this->explanationFixedBy( $definition[ 'disabled_message' ], 'file_repair_areas' );
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

	/**
	 * @return array{
	 *   afs_enabled:bool,
	 *   scan_controls:array<string,bool>,
	 *   repair_controls:array<string,bool>
	 * }
	 */
	private function fileScanningStates() :array {
		$afs = self::con()->comps->scans->AFS();
		$scanAreas = $afs->getFileScanAreas();
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
	 *   is_primary:bool,
	 *   method:string,
	 *   disabled_message:string
	 * }>
	 */
	private function scanControlDefinitions() :array {
		return [
			'malware_php' => [
				'is_primary'       => true,
				'method'           => 'isEnabledMalwareScanPHP',
				'disabled_message' => __( "PHP files aren't scanned for malware.", 'wp-simple-firewall' ),
			],
			'wp' => [
				'is_primary'       => true,
				'method'           => 'isScanEnabledWpCore',
				'disabled_message' => __( "WordPress core files aren't scanned for corruption.", 'wp-simple-firewall' ),
			],
			'plugins' => [
				'is_primary'       => true,
				'method'           => 'isScanEnabledPlugins',
				'disabled_message' => __( "Plugin files aren't scanned for corruption.", 'wp-simple-firewall' ),
			],
			'themes' => [
				'is_primary'       => true,
				'method'           => 'isScanEnabledThemes',
				'disabled_message' => __( "Theme files aren't scanned for corruption.", 'wp-simple-firewall' ),
			],
			'wpcontent' => [
				'is_primary'       => false,
				'method'           => 'isScanEnabledWpContent',
				'disabled_message' => __( "/wp-content/ directory isn't scanned.", 'wp-simple-firewall' ),
			],
			'wproot' => [
				'is_primary'       => false,
				'method'           => 'isScanEnabledWpRoot',
				'disabled_message' => __( "WP root directory isn't scanned.", 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return array<string,array{
	 *   scan_area:string,
	 *   method:string,
	 *   disabled_message:string
	 * }>
	 */
	private function repairControlDefinitions() :array {
		return [
			'wp' => [
				'scan_area'        => 'wp',
				'method'           => 'isRepairFileWP',
				'disabled_message' => __( "WordPress core file auto-repair isn't enabled.", 'wp-simple-firewall' ),
			],
			'plugin' => [
				'scan_area'        => 'plugins',
				'method'           => 'isRepairFilePlugin',
				'disabled_message' => __( "Plugin file auto-repair isn't enabled.", 'wp-simple-firewall' ),
			],
			'theme' => [
				'scan_area'        => 'themes',
				'method'           => 'isRepairFileTheme',
				'disabled_message' => __( "Theme file auto-repair isn't enabled.", 'wp-simple-firewall' ),
			],
		];
	}

	/**
	 * @return list<string>
	 */
	private function selectedRepairAreas() :array {
		$areas = self::con()->opts->optGet( 'file_repair_areas' );
		return \is_array( $areas ) ? \array_values( \array_filter( $areas, 'is_string' ) ) : [];
	}
}
