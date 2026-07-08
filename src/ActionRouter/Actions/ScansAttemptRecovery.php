<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;

class ScansAttemptRecovery extends ScansBase {

	public const SLUG = 'scans_attempt_recovery';

	protected function exec() {
		$scanID = $this->positiveScanIDFromActionData();

		if ( $scanID > 0 && $this->isActiveStaleScanRow( $scanID ) ) {
			self::con()->comps->scans_queue->getQueueWatchdog()->runIfStale();
		}

		$this->response()
			 ->setPayload( $this->buildScanProgressPayload( $scanID > 0 ? [ $scanID ] : [] ) )
			 ->setPayloadSuccess( true );
	}

	private function positiveScanIDFromActionData() :int {
		$value = $this->action_data[ 'scan_id' ] ?? 0;
		if ( !\is_scalar( $value ) || !\is_numeric( $value ) ) {
			return 0;
		}

		$scanID = (int)$value;
		return $scanID > 0 ? $scanID : 0;
	}

	private function isActiveStaleScanRow( int $scanID ) :bool {
		$activeScans = ( new ScansStatus() )->activeScans();
		if ( empty( $activeScans ) ) {
			return false;
		}

		foreach ( self::con()->comps->scans_queue->getActiveScanProgressRows( $activeScans ) as $row ) {
			if ( (int)$row[ 'id' ] === $scanID ) {
				return $row[ 'is_stale' ] === true;
			}
		}

		return false;
	}
}
