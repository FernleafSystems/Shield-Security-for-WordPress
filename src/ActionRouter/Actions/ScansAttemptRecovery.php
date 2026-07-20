<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class ScansAttemptRecovery extends ScansBase {

	public const SLUG = 'scans_attempt_recovery';

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
