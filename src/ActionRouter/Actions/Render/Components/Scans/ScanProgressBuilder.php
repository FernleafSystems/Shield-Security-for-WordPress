<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Init\ScansStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\RunState;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Where the scans stand, for Run Manual Scan and the Actions Queue: the state, its markup, and for the Actions
 * Queue's indicator, what's running now. This is the one place that reads the scans for their progress and decides
 * which scans to show; the renderer only presents what it's given.
 *
 * @phpstan-import-type ActiveScanProgressRow from \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Controller
 * @phpstan-import-type ScanProgressState from ScansProgress
 * @phpstan-type ScanProgressPayload array{
 *   progress_state:'running'|'completed'|'failed',
 *   progress_html:string,
 *   progress_detail:string
 * }
 */
class ScanProgressBuilder {

	use PluginControllerConsumer;

	public const RECENT_RESULT_WINDOW = 600;

	/**
	 * A failure among the scans the page is following comes first. Otherwise the scans are running while any are
	 * active, and once none are, the result is shown for the scans followed, or else the ones that just finished,
	 * or else every scan that's ready.
	 * @param list<int> $trackedScanIDs
	 * @return ScanProgressPayload
	 */
	public function forScans( array $trackedScanIDs ) :array {
		$failure = $this->firstFailure( $trackedScanIDs );
		if ( $failure !== null ) {
			return $this->forFailure( $failure );
		}
		return $this->runningOrFinished( $this->scanSlugsForIDs( $trackedScanIDs ) )
			   ?? $this->payload( ScansProgress::STATE_COMPLETED, [], $this->readyScanSlugs(), '' );
	}

	/**
	 * Where Run Manual Scan opens: on the scans while they run, or on the result of scans someone ran by hand that
	 * just finished. Nothing (null) means it opens on choosing scans.
	 * @return ?ScanProgressPayload
	 */
	public function forOpeningPage() :?array {
		return $this->runningOrFinished( [] );
	}

	/**
	 * @return ScanProgressPayload
	 */
	public function forFailure( string $message ) :array {
		return $this->payload( ScansProgress::STATE_FAILED, [], [], $message );
	}

	/**
	 * The states the page shows by itself: while the start request is on its way, when a request breaks without
	 * saying why, and when updates keep going missing.
	 * @return array{initiating:string,failed:string,stale:string}
	 */
	public function renderPageStates() :array {
		return [
			ScansProgress::STATE_INITIATING => $this->render( ScansProgress::STATE_INITIATING, [], [], '' ),
			ScansProgress::STATE_FAILED     => $this->forFailure( __( "The scan request couldn't be completed.", 'wp-simple-firewall' ) )[ 'progress_html' ],
			ScansProgress::STATE_STALE      => $this->render( ScansProgress::STATE_STALE, [], [], '' ),
		];
	}

	/**
	 * Scans someone ran by hand that finished a full run in the last few minutes, so Run Manual Scan can open on
	 * their result. Scheduled scans don't count: otherwise one that finished in the background would open the page
	 * on a result nobody asked for, in place of choosing scans.
	 * @return list<string>
	 */
	private function recentlyFinishedScanSlugs() :array {
		try {
			$records = self::con()->db_con->scans->getQuerySelector()
				->filterByScans( ScansProgress::SCAN_ORDER )
				->filterByStatus( 'completed' )
				->addWhereEquals( 'scope_type', 'full' )
				->addWhereEquals( 'run_trigger', 'manual' )
				->addWhereNewerThan( Services::Request()->ts() - self::RECENT_RESULT_WINDOW, 'finished_at' )
				->queryWithResult();
		}
		catch ( \Exception $e ) {
			$records = [];
		}
		$finished = \array_map( fn( $record ) :string => (string)$record->scan, \is_array( $records ) ? $records : [] );
		return \array_values( \array_intersect( ScansProgress::SCAN_ORDER, $finished ) );
	}

	/**
	 * Running while any scan is active, with the scans asked for (or else just finished) shown as finished once
	 * they're no longer active; when none is active, the result of those scans. Nothing when there's neither.
	 * @param list<string> $requestedSlugs
	 * @return ?ScanProgressPayload
	 */
	private function runningOrFinished( array $requestedSlugs ) :?array {
		$slugs = empty( $requestedSlugs ) ? $this->recentlyFinishedScanSlugs() : $requestedSlugs;
		$activeRows = self::con()->comps->scans_queue->getActiveScanProgressRows( ( new ScansStatus() )->activeScans() );
		if ( !empty( $activeRows ) ) {
			// The current scan is always the first active row.
			$current = $activeRows[ 0 ];
			$detail = \sprintf( '%s %d%%', ScansProgress::ScanLabel( $current ), $current[ 'progress' ] );
			return $this->payload( ScansProgress::STATE_RUNNING, $activeRows, $slugs, '', $detail );
		}
		return empty( $slugs ) ? null : $this->payload( ScansProgress::STATE_COMPLETED, [], $slugs, '' );
	}

	/**
	 * @return list<string>
	 */
	private function readyScanSlugs() :array {
		$ready = \array_keys( \array_filter(
			self::con()->comps->scans->getAllScanCons(),
			fn( $scanCon ) :bool => $scanCon->isReady()
		) );
		return \array_values( \array_intersect( ScansProgress::SCAN_ORDER, $ready ) );
	}

	/**
	 * @param 'running'|'completed'|'failed' $state
	 * @param list<ActiveScanProgressRow>    $activeRows
	 * @param list<string>                   $scanSlugs
	 * @return ScanProgressPayload
	 */
	private function payload( string $state, array $activeRows, array $scanSlugs, string $failureMessage, string $detail = '' ) :array {
		return [
			'progress_state'  => $state,
			'progress_html'   => $this->render( $state, $activeRows, $scanSlugs, $failureMessage ),
			'progress_detail' => $detail,
		];
	}

	/**
	 * @param ScanProgressState           $state
	 * @param list<ActiveScanProgressRow> $activeRows
	 * @param list<string>                $scanSlugs in scan order
	 */
	private function render( string $state, array $activeRows, array $scanSlugs, string $failureMessage ) :string {
		return self::con()->action_router->render( ScansProgress::class, [
			'state'           => $state,
			'scan_rows'       => $activeRows,
			'scan_slugs'      => $scanSlugs,
			'failure_message' => $failureMessage,
		] );
	}

	/**
	 * The first of the scans, in the order given, that failed, with why.
	 * @param list<int> $scanIDs
	 */
	private function firstFailure( array $scanIDs ) :?string {
		if ( empty( $scanIDs ) ) {
			return null;
		}

		$failed = self::con()->db_con->scans->getQuerySelector()->filterByIDs( $scanIDs )->filterByStatus( 'failed' )->queryWithResult();
		$failedByID = [];
		foreach ( \is_array( $failed ) ? $failed : [] as $scan ) {
			$failedByID[ (int)$scan->id ] = $scan;
		}
		foreach ( $scanIDs as $scanID ) {
			if ( isset( $failedByID[ $scanID ] ) ) {
				return (string)( $failedByID[ $scanID ]->meta[ RunState::META_KEY_LAST_ERROR ]
								 ?? __( 'The scan failed before it could finish.', 'wp-simple-firewall' ) );
			}
		}
		return null;
	}

	/**
	 * @param list<int> $scanIDs
	 * @return list<string> in scan order
	 */
	private function scanSlugsForIDs( array $scanIDs ) :array {
		if ( empty( $scanIDs ) ) {
			return [];
		}
		$scans = self::con()->db_con->scans->getQuerySelector()->filterByIDs( $scanIDs )->queryWithResult();
		$slugs = \array_map( fn( $scan ) :string => (string)$scan->scan, \is_array( $scans ) ? $scans : [] );
		return \array_values( \array_intersect( ScansProgress::SCAN_ORDER, $slugs ) );
	}
}
