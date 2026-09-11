<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\RunState;

/**
 * @phpstan-import-type ActiveScanProgressRow from \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Controller
 * @phpstan-import-type ActiveScanRow from ScansStatus
 * @phpstan-type ScanModalState 'initiating'|'running'|'completed'|'failed'
 */
abstract class ScansBase extends BaseAction {

	use Traits\NonceVerifyRequired;

	public const SCAN_MODAL_STATE_INITIATING = 'initiating';
	public const SCAN_MODAL_STATE_RUNNING = 'running';
	public const SCAN_MODAL_STATE_COMPLETED = 'completed';
	public const SCAN_MODAL_STATE_FAILED = 'failed';

	/**
	 * @param list<int> $startedScanIDs
	 * @return array{
	 *   running:array<string,bool>,
	 *   failed:bool,
	 *   failure_message:string,
	 *   scan_rows:list<ActiveScanProgressRow>,
	 *   modal_state:ScanModalState,
	 *   modal_html:string
	 * }
	 */
	protected function buildScanProgressPayload( array $startedScanIDs = [] ) :array {
		$con = self::con();
		$failedScan = $this->getFailedStartedScan( $startedScanIDs );
		$hasFailedScan = !empty( $failedScan );
		$failureMessage = $hasFailedScan ? $failedScan[ 'message' ] : '';
		$activeScans = [];
		$scanRows = [];
		if ( !$hasFailedScan ) {
			$activeScans = ( new ScansStatus() )->activeScans();
			$scanRows = $con->comps->scans_queue->getActiveScanProgressRows( $activeScans );
		}

		$currentScan = empty( $scanRows ) ? __( 'No scan running.', 'wp-simple-firewall' ) : $scanRows[ 0 ][ 'name' ];
		$enqueued = $this->enqueuedSlugsFromActiveScans( $activeScans );
		$running = \count( $activeScans );
		$modalState = $hasFailedScan
			? self::SCAN_MODAL_STATE_FAILED
			: ( $running === 0 ? self::SCAN_MODAL_STATE_COMPLETED : self::SCAN_MODAL_STATE_RUNNING );

		return \array_merge( [
			'running'         => $con->comps->scans_queue->getScansRunningStates( $enqueued ),
			'failed'          => $hasFailedScan,
			'failure_message' => $failureMessage,
			'scan_rows'       => $scanRows,
		], $this->renderScanModalPayload( $modalState, [
			'current_scan'    => $hasFailedScan ? __( 'Scan failed.', 'wp-simple-firewall' ) : $currentScan,
			'remaining_scans' => $hasFailedScan
				? $failureMessage
				: ( $running === 0 ?
					__( 'No scans remaining.', 'wp-simple-firewall' )
					: sprintf( _n( '%s scan remaining.', '%s scans remaining.', $running, 'wp-simple-firewall' ), $running ) ),
			'progress'        => $hasFailedScan || $modalState === self::SCAN_MODAL_STATE_COMPLETED
				? 100
				: $this->currentScanProgressFromRows( $scanRows ),
			'scan_rows'       => $scanRows,
		] ) );
	}

	/**
	 * @return list<int>
	 */
	protected function startedScanIdsFromActionData() :array {
		return \array_values( \array_unique( \array_filter( \array_map(
			fn( $value ) :int => $this->positiveScanIDFromValue( $value ),
			\is_array( $this->action_data[ 'scan_ids' ] ?? null ) ? $this->action_data[ 'scan_ids' ] : []
		) ) ) );
	}

	/**
	 * @param mixed $value
	 */
	protected function positiveScanIDFromValue( $value ) :int {
		if ( !\is_int( $value ) && !\is_string( $value ) ) {
			return 0;
		}

		$scanID = \filter_var( $value, \FILTER_VALIDATE_INT );
		return \is_int( $scanID ) && $scanID > 0 ? $scanID : 0;
	}

	/**
	 * @param ScanModalState $modalState
	 * @param array{
	 *   current_scan:string,
	 *   remaining_scans:string,
	 *   progress:int|float,
	 *   scan_rows:list<ActiveScanProgressRow>
	 * } $renderData
	 * @return array{modal_state:ScanModalState,modal_html:string}
	 */
	protected function renderScanModalPayload( string $modalState, array $renderData ) :array {
		$progress = (int)\max( 0, \min( 100, \round( (float)$renderData[ 'progress' ] ) ) );

		$renderData[ 'modal_state' ] = $modalState;
		$renderData[ 'progress' ] = $progress;

		return [
			'modal_state' => $modalState,
			'modal_html'  => self::con()->action_router->render( ScansProgress::class, $renderData ),
		];
	}

	/**
	 * @param list<int> $scanIDs
	 * @return array{}|array{id:int,message:string}
	 */
	private function getFailedStartedScan( array $scanIDs ) :array {
		if ( empty( $scanIDs ) ) {
			return [];
		}

		$failedScans = self::con()->db_con->scans->getQuerySelector()
							 ->filterByIDs( $scanIDs )
							 ->filterByStatus( 'failed' )
							 ->queryWithResult();
		if ( empty( $failedScans ) ) {
			return [];
		}

		$failedScansByID = [];
		foreach ( $failedScans as $scan ) {
			$failedScansByID[ $scan->id ] = $scan;
		}

		foreach ( $scanIDs as $scanID ) {
			if ( isset( $failedScansByID[ $scanID ] ) ) {
				$scan = $failedScansByID[ $scanID ];
				return [
					'id'      => $scanID,
					'message' => (string)( $scan->meta[ RunState::META_KEY_LAST_ERROR ] ?? __( 'The scan failed before it could finish.', 'wp-simple-firewall' ) ),
				];
			}
		}

		return [];
	}

	/**
	 * @param list<ActiveScanRow> $activeScans
	 * @return list<string>
	 */
	private function enqueuedSlugsFromActiveScans( array $activeScans ) :array {
		return \array_values( \array_unique( \array_column( $activeScans, 'scan' ) ) );
	}

	/**
	 * @param list<ActiveScanProgressRow> $scanRows
	 */
	private function currentScanProgressFromRows( array $scanRows ) :int {
		if ( empty( $scanRows ) ) {
			return 0;
		}

		return $scanRows[ 0 ][ 'progress' ];
	}
}
