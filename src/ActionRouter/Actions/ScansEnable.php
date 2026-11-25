<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class ScansEnable extends BaseAction {

	public const SLUG = 'scans_enable';

	/**
	 * The file scanner itself, switched off, stops every kind of file being scanned whatever is chosen below it.
	 */
	public const FILE_SCANNING = 'file_scanning';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	/**
	 * What can be switched on, and the setting that does it.
	 * @var array<string,array{option:string,value:string}>
	 */
	public const SCANS = [
		self::FILE_SCANNING => [ 'option' => 'enable_core_file_integrity_scan', 'value' => 'Y' ],
		'wordpress' => [ 'option' => 'file_scan_areas', 'value' => 'wp' ],
		'plugins' => [ 'option' => 'file_scan_areas', 'value' => 'plugins' ],
		'themes' => [ 'option' => 'file_scan_areas', 'value' => 'themes' ],
		'malware' => [ 'option' => 'file_scan_areas', 'value' => 'malware_php' ],
		'vulnerabilities' => [ 'option' => 'enable_wpvuln_scan', 'value' => 'Y' ],
		'abandoned' => [ 'option' => 'enabled_scan_apc', 'value' => 'Y' ],
	];

	/**
	 * Whether the site's plan includes one of them.
	 */
	public static function isInPlan( string $key ) :bool {
		$caps = self::con()->caps;
		switch ( $key ) {
			case 'malware':
				$inPlan = $caps->canScanMalwareLocal();
				break;
			case 'plugins':
			case 'themes':
				$inPlan = $caps->canScanPluginsThemesLocal();
				break;
			case 'vulnerabilities':
				$inPlan = $caps->canScanVulnerabilities();
				break;
			default:
				$inPlan = isset( self::SCANS[ $key ] );
				break;
		}
		return $inPlan;
	}

	/**
	 * Switches on everything asked for, or nothing: one that is unknown or outside the plan refuses them all.
	 */
	protected function exec() {
		$asked = $this->action_data[ 'enable' ];
		$keys = \is_array( $asked ) ? $asked : [];
		$success = !empty( $keys );
		foreach ( $keys as $key ) {
			$success = $success && \is_string( $key ) && isset( self::SCANS[ $key ] ) && self::isInPlan( $key );
		}
		if ( $success ) {
			$opts = self::con()->opts;
			foreach ( $keys as $key ) {
				$scan = self::SCANS[ $key ];
				if ( $scan[ 'option' ] === 'file_scan_areas' ) {
					$opts->optSet( 'file_scan_areas', \array_values( \array_unique(
						\array_merge( $opts->optGet( 'file_scan_areas' ), [ $scan[ 'value' ] ] )
					) ) );
					// A kind of file is only scanned while the file scanner is on.
					$opts->optSet( 'enable_core_file_integrity_scan', 'Y' );
				}
				else {
					$opts->optSet( $scan[ 'option' ], $scan[ 'value' ] );
				}
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
		return [ 'enable' ];
	}
}
