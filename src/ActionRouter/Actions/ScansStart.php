<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScanProgressBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

/**
 * Starts the scans chosen on Run Manual Scan. Once any start, the reply is their progress, as the polling reports it,
 * with the scans to follow; otherwise it's the failure, with why.
 */
class ScansStart extends BaseAction {

	public const SLUG = 'scans_start';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$scans = self::con()->comps->scans;
		$startedScanIDs = [];
		$msg = $scans->getStartBlockedMessage();

		if ( $scans->canStartScans() ) {
			$selectedScans = \array_values( \array_intersect( \array_keys( $this->formParams() ), $scans->getScanSlugs() ) );
			// A ticked checkbox sends "Y"; an unticked one sends nothing.
			$result = $scans->startNewScans( $selectedScans, $this->formFields()->flag( 'opt_clear_ignore', 'Y', 'N', false ) );
			$msg = $result->getMessage();
			$startedScanIDs = $result->getStartedScanIDs();
		}
		elseif ( $msg === '' ) {
			$msg = __( 'Scans cannot execute right now.', 'wp-simple-firewall' );
		}

		$hasStarted = !empty( $startedScanIDs );
		$payload = $hasStarted
			? \array_merge( $this->progress()->forScans( $startedScanIDs ), [ 'scan_ids' => $startedScanIDs ] )
			: $this->progress()->forFailure( $msg );
		$this->response()->setPayload( $payload )->setPayloadSuccess( $hasStarted );
	}

	protected function progress() :ScanProgressBuilder {
		return new ScanProgressBuilder();
	}
}
