<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class ScansAttemptRecovery extends ScansBase {

	public const SLUG = 'scans_attempt_recovery';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$scanID = $this->positiveScanIDFromValue( $this->action_data[ 'scan_id' ] ?? 0 );

		if ( $scanID > 0 ) {
			self::con()->comps->scans_queue->getQueueWatchdog()->recoverScanIfStale( $scanID );
		}

		$this->response()
			 ->setPayload( $this->buildScanProgressPayload( $scanID > 0 ? [ $scanID ] : [] ) )
			 ->setPayloadSuccess( true );
	}
}
