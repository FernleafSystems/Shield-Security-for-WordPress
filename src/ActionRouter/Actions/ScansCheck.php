<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\RunState;

class ScansCheck extends ScansBase {

	public const SLUG = 'scans_check';

	protected function exec() {
		$con = self::con();
		$failedScan = $this->getFailedStartedScan();
		$hasFailedScan = !empty( $failedScan );
		$failureMessage = $hasFailedScan ? $failedScan[ 'message' ] : '';
		$status = new ScansStatus();

		$current = $status->current();
		$currentScan = __( 'No scan running.', 'wp-simple-firewall' );
		if ( !empty( $current ) ) {
			$currentScan = $con->comps->scans->getScanCon( $current )->getScanName();
		}

		$running = \count( $status->enqueued() );

		$this->response()->setPayload( [
			'running' => $con->comps->scans_queue->getScansRunningStates(),
			'failed'  => $hasFailedScan,
			'failure_message' => $failureMessage,
			'vars'    => [
				'progress_html' => self::con()->action_router->render( ScansProgress::class, [
					'current_scan'    => $hasFailedScan ? __( 'Scan failed.', 'wp-simple-firewall' ) : $currentScan,
					'remaining_scans' => $hasFailedScan
						? $failureMessage
						: ( $running === 0 ?
						__( 'No scans remaining.', 'wp-simple-firewall' )
						: sprintf( _n( '%s scan remaining.', '%s scans remaining.', $running, 'wp-simple-firewall' ), $running ) ),
					'progress'        => $hasFailedScan ? 100 : 100*$con->comps->scans_queue->getScanJobProgress(),
					'is_failed'       => $hasFailedScan,
				] ),
			]
		] )->setPayloadSuccess( true );
	}

	/**
	 * @return array{}|array{id:int,message:string}
	 */
	private function getFailedStartedScan() :array {
		$scanIDs = \array_values( \array_filter( \array_map(
			'intval',
			\is_array( $this->action_data[ 'scan_ids' ] ?? null ) ? $this->action_data[ 'scan_ids' ] : []
		) ) );
		if ( empty( $scanIDs ) ) {
			return [];
		}

		foreach ( $scanIDs as $scanID ) {
			$scan = self::con()->db_con->scans->getQuerySelector()->byId( $scanID );
			if ( !empty( $scan ) && $scan->status === 'failed' ) {
				return [
					'id'      => $scanID,
					'message' => (string)( $scan->meta[ RunState::META_KEY_LAST_ERROR ] ?? __( 'The scan failed before it could finish.', 'wp-simple-firewall' ) ),
				];
			}
		}

		return [];
	}
}
