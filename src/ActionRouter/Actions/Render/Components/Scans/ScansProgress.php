<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ScansBase as ScanActionBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * @phpstan-import-type ActiveScanProgressRow from \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Controller
 * @phpstan-import-type ScanModalState from ScanActionBase
 * @phpstan-type PageRow array{
 *   id:int,
 *   name:string,
 *   scope_label:string,
 *   status:'running'|'waiting'|'stalled'|'finished',
 *   status_label:string,
 *   can_attempt_recovery:bool,
 *   progress:int,
 *   aria_label:string
 * }
 */
class ScansProgress extends BaseScans {

	public const SLUG = 'render_scans_progress';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/progress.twig';
	private const TEMPLATE_PAGE = '/wpadmin_pages/insights/scans/run/progress_page.twig';
	public const RECENT_RESULT_WINDOW = 600;
	/**
	 * The order the scans are listed in on Run Manual Scan, from choosing them to their results.
	 */
	public const SCAN_ORDER = [ 'afs', 'wpv', 'apc' ];

	protected function getRenderTemplate() :string {
		return $this->isPageDisplay() ? self::TEMPLATE_PAGE : parent::getRenderTemplate();
	}

	private function isPageDisplay() :bool {
		return $this->action_data[ 'display' ] === ScanActionBase::DISPLAY_PAGE;
	}

	protected function getRenderData() :array {
		$data = $this->buildModalRenderData();
		return $this->isPageDisplay() ? \array_replace_recursive( $data, $this->buildPageRenderData( $data ) ) : $data;
	}

	private function buildModalRenderData() :array {
		/** @var array{modal_state:ScanModalState,current_scan:string,remaining_scans:string,progress:int,scan_rows:list<ActiveScanProgressRow>} $data */
		$data = $this->action_data;
		$modalState = $data[ 'modal_state' ];
		$isInitiating = $modalState === ScanActionBase::SCAN_MODAL_STATE_INITIATING;
		$isFailed = $modalState === ScanActionBase::SCAN_MODAL_STATE_FAILED;
		$isComplete = $modalState === ScanActionBase::SCAN_MODAL_STATE_COMPLETED;
		$isRunning = $isInitiating || $modalState === ScanActionBase::SCAN_MODAL_STATE_RUNNING;
		$currentScan = $data[ 'current_scan' ];
		$remainingScans = $data[ 'remaining_scans' ];
		$progress = $data[ 'progress' ];
		$scanRows = $this->buildScanRows( $data[ 'scan_rows' ] );

		$failedText = __( 'Scan failed.', 'wp-simple-firewall' );
		$completeText = __( 'Scans completed.', 'wp-simple-firewall' );
		$initiatingText = __( 'Preparing scans.', 'wp-simple-firewall' );
		$currentScanText = \sprintf( __( 'Current Scan: %s', 'wp-simple-firewall' ), $currentScan );
		$statusText = $isFailed ? $failedText
			: ( $isComplete ? $completeText
				: ( $isInitiating ? $initiatingText : $currentScanText ) );
		$announcement = $isFailed
			? \trim( $statusText.' '.$remainingScans )
			: \sprintf( '%s %d%%', $statusText, $progress );
		$heading = $isComplete
			? $completeText.' '.__( 'Reloading page', 'wp-simple-firewall' ).'...'
			: $statusText;

		return [
			'strings' => [
				'modal_title'        => __( 'Scan Progress', 'wp-simple-firewall' ),
				'patience_1'         => __( 'File scanning is an intensive operation and takes time.', 'wp-simple-firewall' ),
				'patience_2'         => __( 'We appreciate your patience.', 'wp-simple-firewall' ),
				'progress_label'     => __( 'Scan progress', 'wp-simple-firewall' ),
				'attempt_resume_now' => __( 'Attempt resume now', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'modal_state'     => $modalState,
				'heading'         => $heading,
				'remaining_scans' => $remainingScans,
				'progress'        => $progress,
				'announcement'    => $announcement,
				'is_busy'         => $isRunning,
				'show_progress'   => !$isFailed,
				'scan_rows'       => $scanRows,
				'modal_title_id'  => 'ShieldModalContainerLabel',
			],
		];
	}

	/**
	 * The page shows every scan that was asked for: while they run, the ones already done show as finished;
	 * once they're all done, what each one found and the way on to the Actions Queue.
	 * @param array<string,mixed> $modalData
	 */
	private function buildPageRenderData( array $modalData ) :array {
		$con = self::con();
		$state = $modalData[ 'vars' ][ 'modal_state' ];

		$slugs = \array_values( \array_intersect( self::SCAN_ORDER, $this->pageScanSlugs() ) );

		$results = [];
		$total = 0;
		if ( $state === ScanActionBase::SCAN_MODAL_STATE_COMPLETED ) {
			foreach ( $slugs as $slug ) {
				$scanCon = $con->comps->scans->getScanCon( $slug );
				$count = $this->countIssues( $scanCon );
				$total += $count;
				$results[] = [
					'name'    => $scanCon->getStrings()[ 'name' ],
					'count'   => $count,
					'summary' => $this->resultSummary( $slug, $count ),
				];
			}
		}

		$headingFinished = $total > 0
			? \sprintf( _n( 'Scan finished: %s issue found', 'Scan finished: %s issues found', $total, 'wp-simple-firewall' ), $total )
			: __( 'Scan finished: nothing found', 'wp-simple-firewall' );

		$pageRows = $this->buildPageRows( $slugs );
		$finishedRows = \count( \array_filter( $pageRows, fn( array $row ) :bool => $row[ 'status' ] === 'finished' ) );

		$page = [
			'strings' => [
				'scanning_title'   => __( 'Scanning your site', 'wp-simple-firewall' ),
				'leave_note'       => __( 'You can leave this page: the results land in the Actions Queue.', 'wp-simple-firewall' ),
				'finished_title'   => $headingFinished,
				'finished_desc'    => $total > 0
					? __( 'Each one is waiting in the Actions Queue, with what to do about it.', 'wp-simple-firewall' )
					: __( 'The scans you ran found nothing that needs you.', 'wp-simple-firewall' ),
				'review_queue'     => __( 'Review in the Actions Queue', 'wp-simple-firewall' ),
				'open_queue'       => __( 'Open the Actions Queue', 'wp-simple-firewall' ),
				'scan_again'       => __( 'Scan again', 'wp-simple-firewall' ),
				'try_again'        => __( 'Try again', 'wp-simple-firewall' ),
				/* translators: %1$s: scans finished so far, %2$s: scans in all */
				'overall_progress' => \sprintf( _n( '%1$s of %2$s scan finished', '%1$s of %2$s scans finished', \count( $pageRows ), 'wp-simple-firewall' ),
					$finishedRows, \count( $pageRows ) ),
			],
			'hrefs'   => [
				'actions_queue' => $con->plugin_urls->actionsQueueScans(),
				// Back to choosing, even straight after a scan, when the page would otherwise open on its result.
				'scan_run'      => URL::Build( $con->plugin_urls->scansRun(), [ 'step' => 'choose' ] ),
			],
			'vars'    => [
				'page_rows'        => $pageRows,
				'overall_progress' => empty( $pageRows ) ? 0 : (int)\round( 100*$finishedRows/\count( $pageRows ) ),
				'results'          => $results,
				'total_issues'     => $total,
			],
		];
		if ( $state === ScanActionBase::SCAN_MODAL_STATE_COMPLETED ) {
			$page[ 'vars' ][ 'heading' ] = $headingFinished;
			$page[ 'vars' ][ 'announcement' ] = $headingFinished;
		}
		return $page;
	}

	/**
	 * @return list<string>
	 */
	private function pageScanSlugs() :array {
		$slugs = $this->action_data[ 'scan_slugs' ];
		if ( empty( $slugs ) ) {
			// Picked up after a reload, without the started scans to go on: the ones running and the ones just finished.
			$slugs = \array_unique( \array_merge(
				\array_column( $this->action_data[ 'scan_rows' ], 'scan' ),
				self::RecentlyFinishedScanSlugs()
			) );
		}
		if ( empty( $slugs ) ) {
			$slugs = \array_keys( \array_filter(
				self::con()->comps->scans->getAllScanCons(),
				fn( $scanCon ) :bool => $scanCon->isReady()
			) );
		}
		return $slugs;
	}

	/**
	 * Scans that finished a full run in the last few minutes, so Run Manual Scan can open on their result.
	 * @return list<string>
	 */
	public static function RecentlyFinishedScanSlugs( int $withinSeconds = self::RECENT_RESULT_WINDOW ) :array {
		$cutoff = Services::Request()->ts() - $withinSeconds;
		return \array_keys( \array_filter(
			self::LastFinishedBySlug(),
			fn( int $finishedAt ) :bool => $finishedAt > $cutoff
		) );
	}

	/**
	 * When each scan last finished a full run, or 0 if it never has.
	 * @return array<string,int>
	 */
	public static function LastFinishedBySlug() :array {
		$latest = self::con()->comps->site_query->latestCompletedScanTimestamps();
		return [
			'afs' => (int)$latest[ 'malware' ],
			'wpv' => (int)$latest[ 'vulnerabilities' ],
			'apc' => (int)$latest[ 'abandoned' ],
		];
	}

	/**
	 * One row for each scan asked for. Scans not in the active rows have finished, unless nothing is active yet
	 * because the scans are still being set up.
	 * @param list<string> $slugs
	 * @return list<PageRow>
	 */
	private function buildPageRows( array $slugs ) :array {
		$con = self::con();
		$activeBySlug = [];
		foreach ( $this->action_data[ 'scan_rows' ] as $row ) {
			$activeBySlug[ $row[ 'scan' ] ] ??= [
				'id'                   => $row[ 'id' ],
				'name'                 => $row[ 'name' ],
				'scope_label'          => $this->scopeLabel( $row[ 'scope_type' ], $row[ 'scope_key' ] ),
				'status'               => $row[ 'display_status' ],
				'status_label'         => $this->statusPresentation( $row[ 'display_status' ] )[ 'label' ],
				'can_attempt_recovery' => $row[ 'can_attempt_recovery' ],
				'progress'             => $row[ 'progress' ],
				'aria_label'           => $this->progressLabel( $row[ 'name' ] ),
			];
		}
		$nothingActiveYet = empty( $activeBySlug );

		$rows = [];
		foreach ( $slugs as $slug ) {
			if ( isset( $activeBySlug[ $slug ] ) ) {
				$rows[] = $activeBySlug[ $slug ];
				continue;
			}
			$name = $con->comps->scans->getScanCon( $slug )->getStrings()[ 'name' ];
			$rows[] = [
				'id'                   => 0,
				'name'                 => $name,
				'scope_label'          => '',
				'status'               => $nothingActiveYet ? 'waiting' : 'finished',
				'status_label'         => $nothingActiveYet
					? $this->statusPresentation( 'waiting' )[ 'label' ]
					: __( 'finished', 'wp-simple-firewall' ),
				'can_attempt_recovery' => false,
				'progress'             => $nothingActiveYet ? 0 : 100,
				'aria_label'           => $this->progressLabel( $name ),
			];
		}
		return $rows;
	}

	private function progressLabel( string $scanName ) :string {
		return \sprintf( __( '%s progress', 'wp-simple-firewall' ), $scanName );
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Base $scanCon
	 */
	private function countIssues( $scanCon ) :int {
		$counts = new Counts();
		switch ( $scanCon->getSlug() ) {
			case 'wpv':
				return $counts->countVulnerableAssets();
			case 'apc':
				return $counts->countAbandoned();
			default:
				return ( new RetrieveCount() )->setScanController( $scanCon )->count();
		}
	}

	private function resultSummary( string $slug, int $count ) :string {
		if ( $count === 0 ) {
			return __( 'Nothing found', 'wp-simple-firewall' );
		}
		switch ( $slug ) {
			case 'afs':
				$text = _n( '%s file to review', '%s files to review', $count, 'wp-simple-firewall' );
				break;
			case 'wpv':
				$text = _n( '%s vulnerable plugin or theme', '%s vulnerable plugins or themes', $count, 'wp-simple-firewall' );
				break;
			case 'apc':
				$text = _n( '%s abandoned plugin', '%s abandoned plugins', $count, 'wp-simple-firewall' );
				break;
			default:
				$text = _n( '%s issue', '%s issues', $count, 'wp-simple-firewall' );
				break;
		}
		return \sprintf( $text, $count );
	}

	/**
	 * @param list<ActiveScanProgressRow> $rows
	 * @return list<array{id:int,name:string,scope_label:string,status_label:string,status_icon_class:string,status_class:string,progress_bar_class:string,can_attempt_recovery:bool,progress:int,aria_label:string}>
	 */
	private function buildScanRows( array $rows ) :array {
		$renderRows = [];
		foreach ( $rows as $row ) {
			$statusPresentation = $this->statusPresentation( $row[ 'display_status' ] );
			$renderRows[] = [
				'id'                   => $row[ 'id' ],
				'name'                 => $row[ 'name' ],
				'scope_label'          => $this->scopeLabel( $row[ 'scope_type' ], $row[ 'scope_key' ] ),
				'status_label'         => $statusPresentation[ 'label' ],
				'status_icon_class'    => $statusPresentation[ 'icon_class' ],
				'status_class'         => $statusPresentation[ 'status_class' ],
				'progress_bar_class'   => $statusPresentation[ 'progress_bar_class' ],
				'can_attempt_recovery' => $row[ 'can_attempt_recovery' ],
				'progress'             => $row[ 'progress' ],
				'aria_label'           => $this->progressLabel( $row[ 'name' ] ),
			];
		}
		return $renderRows;
	}

	private function scopeLabel( string $scopeType, string $scopeKey ) :string {
		if ( $scopeType === 'full' || $scopeType === '' ) {
			return '';
		}

		if ( $scopeType === 'core' ) {
			return __( 'Core', 'wp-simple-firewall' );
		}

		if ( $scopeType === 'plugin' ) {
			return sprintf( __( 'Plugin: %s', 'wp-simple-firewall' ), $scopeKey );
		}

		if ( $scopeType === 'theme' ) {
			return sprintf( __( 'Theme: %s', 'wp-simple-firewall' ), $scopeKey );
		}

		return $scopeKey === '' ? $scopeType : sprintf( '%s: %s', $scopeType, $scopeKey );
	}

	/**
	 * @param 'running'|'waiting'|'stalled' $status
	 * @return array{label:string,icon_class:string,status_class:string,progress_bar_class:string}
	 */
	private function statusPresentation( string $status ) :array {
		switch ( $status ) {
			case 'running':
				return [
					'label'              => __( 'running', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-arrow-repeat',
					'status_class'       => 'shield-scan-progress__status--running',
					'progress_bar_class' => 'bg-success progress-bar-striped progress-bar-animated',
				];

			case 'waiting':
				return [
					'label'              => __( 'waiting', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-hourglass-split',
					'status_class'       => 'shield-scan-progress__status--waiting',
					'progress_bar_class' => 'bg-secondary',
				];

			case 'stalled':
				return [
					'label'              => __( 'appears stalled', 'wp-simple-firewall' ),
					'icon_class'         => 'bi bi-exclamation-triangle-fill',
					'status_class'       => 'shield-scan-progress__status--stalled',
					'progress_bar_class' => 'bg-warning progress-bar-striped',
				];

			default:
				throw new \UnexpectedValueException( 'Unsupported scan progress status.' );
		}
	}

	protected function getRequiredDataKeys() :array {
		return [
			'modal_state',
			'current_scan',
			'remaining_scans',
			'progress',
			'scan_rows',
			'scan_slugs',
			'display',
		];
	}
}
