<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Counts;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Services\Utilities\URL;

/**
 * Run Manual Scan's progress, in whichever state the scans are in. The server reports scans as running, completed
 * or failed. The page shows some states itself: scans being prepared while the start request is on its way, a
 * failure when a request breaks without the server saying why, and stale when it can't get an update. Those are
 * rendered once with the page and shown from there.
 *
 * ScanProgressBuilder decides what's shown: the active scans, and the scans (in scan order) to show as finished
 * while others run, or whose results to show once they've all finished.
 *
 * @phpstan-import-type ActiveScanProgressRow from \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue\Controller
 * @phpstan-type ScanProgressState 'initiating'|'running'|'completed'|'failed'|'stale'
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
	public const TEMPLATE = '/wpadmin_pages/insights/scans/run/progress_page.twig';
	/**
	 * The order the scans are listed in on Run Manual Scan, from choosing them to their results.
	 */
	public const SCAN_ORDER = [ 'afs', 'wpv', 'apc' ];

	public const STATE_INITIATING = 'initiating';
	public const STATE_RUNNING = 'running';
	public const STATE_COMPLETED = 'completed';
	public const STATE_FAILED = 'failed';
	public const STATE_STALE = 'stale';

	protected function getRenderData() :array {
		/** @var ScanProgressState $state */
		$state = $this->action_data[ 'state' ];
		switch ( $state ) {
			case self::STATE_INITIATING:
				$data = $this->initiatingData();
				break;
			case self::STATE_RUNNING:
				$data = $this->runningData();
				break;
			case self::STATE_COMPLETED:
				$data = $this->completedData();
				break;
			case self::STATE_FAILED:
				$data = $this->failedData();
				break;
			case self::STATE_STALE:
				$data = $this->staleData();
				break;
			default:
				throw new \UnexpectedValueException( 'Unsupported scan progress state.' );
		}
		$data[ 'vars' ][ 'state' ] = $state;
		$data[ 'vars' ][ 'is_busy' ] = \in_array( $state, [ self::STATE_INITIATING, self::STATE_RUNNING ], true );
		return $data;
	}

	private function initiatingData() :array {
		$heading = __( 'Preparing scans.', 'wp-simple-firewall' );
		return [
			'strings' => [
				'wait' => __( 'Please wait while the scan request starts.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'heading'      => $heading,
				'announcement' => $heading,
			],
		];
	}

	/**
	 * The current scan is always the first active row.
	 */
	private function runningData() :array {
		/** @var list<ActiveScanProgressRow> $activeRows */
		$activeRows = $this->action_data[ 'scan_rows' ];
		$current = $activeRows[ 0 ];
		/* translators: %s: the scan running now */
		$heading = \sprintf( __( 'Current Scan: %s', 'wp-simple-firewall' ), self::ScanLabel( $current ) );

		$pageRows = $this->buildPageRows( $activeRows );
		$finished = \count( \array_filter( $pageRows, fn( array $row ) :bool => $row[ 'status' ] === 'finished' ) );
		return [
			'strings' => [
				'scanning_title'     => __( 'Scanning your site', 'wp-simple-firewall' ),
				'progress_label'     => __( 'Scan progress', 'wp-simple-firewall' ),
				/* translators: %1$s: scans finished so far, %2$s: scans in all */
				'overall_progress'   => \sprintf( _n( '%1$s of %2$s scan finished', '%1$s of %2$s scans finished', \count( $pageRows ), 'wp-simple-firewall' ),
					$finished, \count( $pageRows ) ),
				'attempt_resume_now' => __( 'Attempt resume now', 'wp-simple-firewall' ),
				'patience'           => __( 'File scanning is an intensive operation and takes time.', 'wp-simple-firewall' ),
				'leave_note'         => __( 'You can leave this page: the results land in the Actions Queue.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'heading'          => $heading,
				'announcement'     => \sprintf( '%s %d%%', $heading, $current[ 'progress' ] ),
				'page_rows'        => $pageRows,
				'overall_progress' => (int)\round( 100*$finished/\count( $pageRows ) ),
			],
		];
	}

	/**
	 * What each scan asked for found, and the way on to the Actions Queue.
	 */
	private function completedData() :array {
		$con = self::con();
		$results = [];
		$total = 0;
		foreach ( $this->action_data[ 'scan_slugs' ] as $slug ) {
			$scanCon = $con->comps->scans->getScanCon( $slug );
			$count = $this->countIssues( $scanCon );
			$total += $count;
			$results[] = [
				'name'    => $scanCon->getStrings()[ 'name' ],
				'count'   => $count,
				'summary' => $this->resultSummary( $slug, $count ),
			];
		}

		$heading = $total > 0
			/* translators: %s: how many issues the scans found */
			? \sprintf( _n( 'Scan finished: %s issue found', 'Scan finished: %s issues found', $total, 'wp-simple-firewall' ), $total )
			: __( 'Scan finished: nothing found', 'wp-simple-firewall' );
		return [
			'strings' => [
				'finished_title' => $heading,
				'finished_desc'  => $total > 0
					? __( 'Each one is waiting in the Actions Queue, with what to do about it.', 'wp-simple-firewall' )
					: __( 'The scans you ran found nothing that needs you.', 'wp-simple-firewall' ),
				'review_queue'   => __( 'Review in the Actions Queue', 'wp-simple-firewall' ),
				'open_queue'     => __( 'Open the Actions Queue', 'wp-simple-firewall' ),
				'scan_again'     => __( 'Scan again', 'wp-simple-firewall' ),
			],
			'hrefs'   => [
				'actions_queue' => $con->plugin_urls->actionsQueueScans(),
				'scan_again'    => $this->chooseScansHref(),
			],
			'vars'    => [
				'announcement' => $heading,
				'results'      => $results,
				'total_issues' => $total,
			],
		];
	}

	private function failedData() :array {
		$heading = __( 'Scan failed.', 'wp-simple-firewall' );
		$message = $this->action_data[ 'failure_message' ];
		return [
			'vars' => [
				'announcement' => \trim( $heading.' '.$message ),
				'notice'       => [
					'tone'       => 'is-critical',
					'icon'       => 'bi-x-lg',
					'title'      => $heading,
					'message'    => $message,
					'href'       => $this->chooseScansHref(),
					'link_label' => __( 'Try again', 'wp-simple-firewall' ),
				],
			],
		];
	}

	/**
	 * The scans may be fine: it's only their progress that can't be reached at the moment.
	 */
	private function staleData() :array {
		$heading = __( "Scan progress couldn't be updated.", 'wp-simple-firewall' );
		$message = __( 'Your scans may still be running.', 'wp-simple-firewall' );
		return [
			'vars' => [
				'announcement' => $heading.' '.$message,
				'notice'       => [
					'tone'       => 'is-warning',
					'icon'       => 'bi-exclamation-triangle',
					'title'      => $heading,
					'message'    => $message,
					'href'       => self::con()->plugin_urls->scansRun(),
					'link_label' => __( 'Refresh', 'wp-simple-firewall' ),
				],
			],
		];
	}

	/**
	 * Back to choosing, even straight after a scan, when the page would otherwise open on its result.
	 */
	private function chooseScansHref() :string {
		return URL::Build( self::con()->plugin_urls->scansRun(), [ 'step' => 'choose' ] );
	}

	/**
	 * A scan's name, with what it's limited to when it doesn't cover the whole site, so that scans of one kind
	 * running for different scopes can be told apart.
	 * @param ActiveScanProgressRow $row
	 */
	public static function ScanLabel( array $row ) :string {
		$scope = self::scopeLabel( $row[ 'scope_type' ], $row[ 'scope_key' ] );
		/* translators: %1$s: a scan's name, %2$s: what the scan is limited to, such as one plugin */
		return $scope === '' ? $row[ 'name' ] : \sprintf( __( '%1$s (%2$s)', 'wp-simple-firewall' ), $row[ 'name' ], $scope );
	}

	/**
	 * In scan order: every active scan, each with its own row since one kind can be running for several scopes at
	 * once, and a finished row for each scan given that's no longer active.
	 * @param list<ActiveScanProgressRow> $activeRows
	 * @return list<PageRow>
	 */
	private function buildPageRows( array $activeRows ) :array {
		$finishedSlugs = $this->action_data[ 'scan_slugs' ];
		$rows = [];
		foreach ( self::SCAN_ORDER as $slug ) {
			$active = \array_filter( $activeRows, fn( array $row ) :bool => $row[ 'scan' ] === $slug );
			foreach ( $active as $row ) {
				$rows[] = [
					'id'                   => $row[ 'id' ],
					'name'                 => $row[ 'name' ],
					'scope_label'          => self::scopeLabel( $row[ 'scope_type' ], $row[ 'scope_key' ] ),
					'status'               => $row[ 'display_status' ],
					'status_label'         => $this->statusLabel( $row[ 'display_status' ] ),
					'can_attempt_recovery' => $row[ 'can_attempt_recovery' ],
					'progress'             => $row[ 'progress' ],
					'aria_label'           => $this->progressLabel( self::ScanLabel( $row ) ),
				];
			}
			if ( empty( $active ) && \in_array( $slug, $finishedSlugs, true ) ) {
				$name = self::con()->comps->scans->getScanCon( $slug )->getStrings()[ 'name' ];
				$rows[] = [
					'id'                   => 0,
					'name'                 => $name,
					'scope_label'          => '',
					'status'               => 'finished',
					'status_label'         => $this->statusLabel( 'finished' ),
					'can_attempt_recovery' => false,
					'progress'             => 100,
					'aria_label'           => $this->progressLabel( $name ),
				];
			}
		}
		return $rows;
	}

	private function progressLabel( string $scanLabel ) :string {
		/* translators: %s: a scan, such as its name */
		return \sprintf( __( '%s progress', 'wp-simple-firewall' ), $scanLabel );
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
				/* translators: %s: how many files the scan found */
				$text = _n( '%s file to review', '%s files to review', $count, 'wp-simple-firewall' );
				break;
			case 'wpv':
				/* translators: %s: how many plugins and themes the scan found */
				$text = _n( '%s vulnerable plugin or theme', '%s vulnerable plugins or themes', $count, 'wp-simple-firewall' );
				break;
			case 'apc':
				/* translators: %s: how many plugins the scan found */
				$text = _n( '%s abandoned plugin', '%s abandoned plugins', $count, 'wp-simple-firewall' );
				break;
			default:
				/* translators: %s: how many issues the scan found */
				$text = _n( '%s issue', '%s issues', $count, 'wp-simple-firewall' );
				break;
		}
		return \sprintf( $text, $count );
	}

	private static function scopeLabel( string $scopeType, string $scopeKey ) :string {
		if ( $scopeType === 'full' || $scopeType === '' ) {
			return '';
		}

		if ( $scopeType === 'core' ) {
			return __( 'Core', 'wp-simple-firewall' );
		}

		if ( $scopeType === 'plugin' ) {
			/* translators: %s: the plugin a scan is limited to */
			return \sprintf( __( 'Plugin: %s', 'wp-simple-firewall' ), $scopeKey );
		}

		if ( $scopeType === 'theme' ) {
			/* translators: %s: the theme a scan is limited to */
			return \sprintf( __( 'Theme: %s', 'wp-simple-firewall' ), $scopeKey );
		}

		return $scopeKey === '' ? $scopeType : \sprintf( '%s: %s', $scopeType, $scopeKey );
	}

	/**
	 * @param 'running'|'waiting'|'stalled'|'finished' $status
	 */
	private function statusLabel( string $status ) :string {
		switch ( $status ) {
			case 'running':
				return __( 'running', 'wp-simple-firewall' );
			case 'waiting':
				return __( 'waiting', 'wp-simple-firewall' );
			case 'stalled':
				return __( 'appears stalled', 'wp-simple-firewall' );
			case 'finished':
				return __( 'finished', 'wp-simple-firewall' );
			default:
				throw new \UnexpectedValueException( 'Unsupported scan progress status.' );
		}
	}

	protected function getRequiredDataKeys() :array {
		return [
			'state',
			'scan_rows',
			'scan_slugs',
			'failure_message',
		];
	}
}
