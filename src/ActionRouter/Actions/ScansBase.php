<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ScansProgress;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\RunState;

abstract class ScansBase extends BaseAction {

	use Traits\NonceVerifyRequired;

	public const SCAN_MODAL_STATE_INITIATING = 'initiating';
	public const SCAN_MODAL_STATE_RUNNING = 'running';
	public const SCAN_MODAL_STATE_COMPLETED = 'completed';
	public const SCAN_MODAL_STATE_FAILED = 'failed';

	/**
	 * @param int[] $startedScanIDs
	 * @return array{
	 *   running:array<string,bool>,
	 *   failed:bool,
	 *   failure_message:string,
	 *   scan_rows:list<array{id:int,scan:string,name:string,scope_type:string,scope_key:string,raw_status:string,display_status:string,is_current:bool,is_stale:bool,can_attempt_recovery:bool,progress:int,total_items:int,unfinished:int}>,
	 *   modal_state:string,
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
				: $this->aggregateProgressFromRows( $scanRows ),
			'scan_rows'       => $scanRows,
		] ) );
	}

	/**
	 * @return int[]
	 */
	protected function startedScanIdsFromActionData() :array {
		return \array_values( \array_filter( \array_map(
			'intval',
			\is_array( $this->action_data[ 'scan_ids' ] ?? null ) ? $this->action_data[ 'scan_ids' ] : []
		), static fn( int $scanID ) :bool => $scanID > 0 ) );
	}

	/**
	 * @param array{
	 *   current_scan:string,
	 *   remaining_scans:string,
	 *   progress:int|float,
	 *   scan_rows?:list<array{id:int,scan:string,name:string,scope_type:string,scope_key:string,raw_status:string,display_status:string,is_current:bool,is_stale:bool,can_attempt_recovery:bool,progress:int,total_items:int,unfinished:int}>
	 * } $renderData
	 * @return array{modal_state:string,modal_html:string}
	 */
	protected function renderScanModalPayload( string $modalState, array $renderData ) :array {
		$modalState = $this->normaliseScanModalState( $modalState );
		$progress = (int)\max( 0, \min( 100, \round( (float)$renderData[ 'progress' ] ) ) );

		$renderData = \array_merge( [
			'scan_rows' => [],
		], $renderData, [
			'modal_state' => $modalState,
			'progress'    => $progress,
		] );

		return [
			'modal_state' => $modalState,
			'modal_html'  => self::con()->action_router->render( ScansProgress::class, $renderData ),
		];
	}

	/**
	 * @param int[] $scanIDs
	 * @return array{}|array{id:int,message:string}
	 */
	private function getFailedStartedScan( array $scanIDs ) :array {
		$scanIDs = \array_values( \array_filter(
			\array_map( 'intval', $scanIDs ),
			static fn( int $scanID ) :bool => $scanID > 0
		) );
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
			$failedScansByID[ (int)$scan->id ] = $scan;
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
	 * @param list<array{id:int,scan:string,status:string,scope_type:string,scope_key:string,created_at:int,started_at:int,ready_at:int,last_process_at:int}> $activeScans
	 * @return string[]
	 */
	private function enqueuedSlugsFromActiveScans( array $activeScans ) :array {
		$enqueued = [];
		foreach ( $activeScans as $scan ) {
			if ( $scan[ 'scan' ] !== '' ) {
				$enqueued[] = $scan[ 'scan' ];
			}
		}
		return \array_values( \array_unique( $enqueued ) );
	}

	/**
	 * @param list<array{id:int,scan:string,name:string,scope_type:string,scope_key:string,raw_status:string,display_status:string,is_current:bool,is_stale:bool,can_attempt_recovery:bool,progress:int,total_items:int,unfinished:int}> $scanRows
	 */
	private function aggregateProgressFromRows( array $scanRows ) :int {
		if ( empty( $scanRows ) ) {
			return 0;
		}

		return (int)\round( \array_sum( \array_column( $scanRows, 'progress' ) )/\count( $scanRows ) );
	}

	private function normaliseScanModalState( string $modalState ) :string {
		return \in_array( $modalState, [
			self::SCAN_MODAL_STATE_INITIATING,
			self::SCAN_MODAL_STATE_RUNNING,
			self::SCAN_MODAL_STATE_COMPLETED,
			self::SCAN_MODAL_STATE_FAILED,
		], true ) ? $modalState : self::SCAN_MODAL_STATE_RUNNING;
	}
}
