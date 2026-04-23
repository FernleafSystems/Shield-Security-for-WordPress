<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\StartScansResult;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;

class ScansStart extends ScansBase {

	public const SLUG = 'scans_start';

	protected function exec() {
		$con = self::con();
		$success = false;
		$reloadPage = false;
		$startedScanIDs = [];
		$errorCode = '';
		$failures = [];
		$msg = $con->comps->scans->getStartBlockedMessage();
		if ( $msg !== '' ) {
			$errorCode = StartScansResult::CODE_START_BLOCKED;
		}

		$params = FormParams::Retrieve();
		if ( $con->comps->scans->canStartScans() ) {
			$selectedScans = \array_intersect( \array_keys( $params ), $con->comps->scans->getScanSlugs() );
			$resetIgnore = (bool)( $params[ 'opt_clear_ignore' ] ?? false );
			$result = $con->comps->scans->startNewScans( $selectedScans, $resetIgnore );
			$startedScanIDs = $result->getStartedScanIDs();
			$failures = $result->getFailures();
			$msg = $result->getMessage();
			$errorCode = $result->getErrorCode();
			if ( $result->hasStarted() ) {
				$success = true;
				$reloadPage = true;
			}
		}
		elseif ( $msg === '' ) {
			$msg = __( 'Scans cannot execute right now.', 'wp-simple-firewall' );
		}

		$isScanRunning = $con->comps->scans_queue->hasRunningScans();

		$payload = [
			'scans_running' => $isScanRunning,
			'page_reload'   => $reloadPage && !$isScanRunning,
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

		$this->response()->setPayload( $payload )->setPayloadSuccess( $success );
	}
}
