<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;

class ScansStart extends ScansBase {

	public const SLUG = 'scans_start';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$con = self::con();
		$success = false;
		$startedScanIDs = [];
		$selectedScans = [];
		$errorCode = '';
		$failures = [];
		$msg = $con->comps->scans->getStartBlockedMessage();
		if ( $msg !== '' ) {
			$errorCode = StartScansResult::CODE_START_BLOCKED;
		}

		$params = $this->formParams();
		if ( $con->comps->scans->canStartScans() ) {
			$selectedScans = \array_values( \array_intersect( \array_keys( $params ), $con->comps->scans->getScanSlugs() ) );
			// A ticked checkbox sends "Y"; an unticked one sends nothing.
			$resetIgnore = $this->formFields()->flag( 'opt_clear_ignore', 'Y', 'N', false );
			$result = $con->comps->scans->startNewScans( $selectedScans, $resetIgnore );
			$startedScanIDs = $result->getStartedScanIDs();
			$failures = $result->getFailures();
			$msg = $result->getMessage();
			$errorCode = $result->getErrorCode();
			if ( $result->hasStarted() ) {
				$success = true;
			}
		}
		elseif ( $msg === '' ) {
			$msg = __( 'Scans cannot execute right now.', 'wp-simple-firewall' );
		}

		$isScanRunning = $con->comps->scans_queue->hasRunningScans();

		$payload = [
			'scans_running' => $isScanRunning,
			// Run Manual Scan, where scans start, shows the result itself.
			'page_reload'   => false,
			'scan_ids'      => $startedScanIDs,
			'message'       => $msg,
		];
		if ( $errorCode !== '' ) {
			$payload[ 'error_code' ] = $errorCode;
		}
		if ( $errorCode === StartScansResult::CODE_START_BLOCKED ) {
			$payload[ 'blocked_reasons' ] = $con->comps->scans->getReasonsScansCantExecute();
		}
		if ( !empty( $failures ) ) {
			$payload[ 'start_failures' ] = $failures;
		}

		$modalState = $success
			? ( $isScanRunning ? self::SCAN_MODAL_STATE_RUNNING : self::SCAN_MODAL_STATE_COMPLETED )
			: self::SCAN_MODAL_STATE_FAILED;

		$payload = \array_merge( $payload, $this->renderScanModalPayload( $modalState, [
			'current_scan'    => $success
				? ( $isScanRunning ? __( 'Preparing scans.', 'wp-simple-firewall' ) : __( 'Scans completed.', 'wp-simple-firewall' ) )
				: __( 'Scan failed.', 'wp-simple-firewall' ),
			'remaining_scans' => $msg,
			'progress'        => $isScanRunning ? 5 : 100,
			'scan_rows'       => [],
			'scan_slugs'      => $selectedScans,
		] ) );

		$this->response()->setPayload( $payload )->setPayloadSuccess( $success );
	}
}
