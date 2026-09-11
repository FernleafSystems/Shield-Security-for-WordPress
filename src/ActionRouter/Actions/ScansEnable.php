<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class ScansEnable extends ScansBase {

	public const SLUG = 'scans_enable';

	/** @var array<string,array{option:string,value:string}> */
	public const SCANS = [
		'malware' => [ 'option' => 'file_scan_areas', 'value' => 'malware_php' ],
		'plugins' => [ 'option' => 'file_scan_areas', 'value' => 'plugins' ],
		'themes' => [ 'option' => 'file_scan_areas', 'value' => 'themes' ],
		'wordpress' => [ 'option' => 'file_scan_areas', 'value' => 'wp' ],
		'vulnerabilities' => [ 'option' => 'enable_wpvuln_scan', 'value' => 'Y' ],
		'abandoned' => [ 'option' => 'enabled_scan_apc', 'value' => 'Y' ],
	];

	protected function exec() {
		$key = $this->action_data[ 'scan' ];
		$scan = \is_string( $key ) ? ( self::SCANS[ $key ] ?? null ) : null;
		$success = $scan !== null && self::con()->isPremiumActive();
		if ( $success ) {
			switch ( $key ) {
				case 'malware':
					$success = self::con()->caps->canScanMalwareLocal();
					break;
				case 'plugins':
				case 'themes':
					$success = self::con()->caps->canScanPluginsThemesLocal();
					break;
				case 'vulnerabilities':
					$success = self::con()->caps->canScanVulnerabilities();
					break;
			}
		}
		if ( $success ) {
			$opts = self::con()->opts;
			if ( $scan[ 'option' ] === 'file_scan_areas' ) {
				$areas = $opts->optGet( 'file_scan_areas' );
				$areas[] = $scan[ 'value' ];
				$opts->optSet( 'file_scan_areas', \array_values( \array_unique( $areas ) ) );
				$opts->optSet( 'enable_core_file_integrity_scan', 'Y' );
			}
			else {
				$opts->optSet( $scan[ 'option' ], $scan[ 'value' ] );
			}
			$opts->store();
		}
		$this->response()->setPayload( [
			'message' => $success ? __( 'Scanning enabled.', 'wp-simple-firewall' )
				: __( 'This protection could not be enabled.', 'wp-simple-firewall' ),
			'page_reload' => false,
		] )->setPayloadSuccess( $success );
	}

	protected function getRequiredDataKeys() :array {
		return [ 'scan' ];
	}
}
