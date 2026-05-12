<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @phpstan-type HeadlineZones array{
 *   total:int,
 *   good:int,
 *   warning:int,
 *   critical:int
 * }
 * @phpstan-type HeadlineSummary array{
 *   title:string,
 *   subtitle:string,
 *   state:'attention'|'all_clear',
 *   total_issues:int
 * }
 * @phpstan-type HeadlineAttentionCard array{
 *   key:'attention',
 *   label:string,
 *   value:string,
 *   meta:string,
 *   state:'attention'|'all_clear',
 *   severity:'good'|'warning'|'critical',
 *   total_issues:int
 * }
 * @phpstan-type HeadlineCoverageCard array{
 *   key:'coverage',
 *   label:string,
 *   value:string,
 *   meta:string,
 *   severity:'good'|'warning'|'critical',
 *   percentage:int,
 *   zones:HeadlineZones
 * }
 * @phpstan-type HeadlineScansCard array{
 *   key:'scans',
 *   label:string,
 *   value:string,
 *   meta:string,
 *   state:'running'|'completed'|'not_started',
 *   enqueued_count:int,
 *   latest_completed_at:int
 * }
 * @phpstan-type InfoHeadlineContract array{
 *   summary:HeadlineSummary,
 *   cards:list<HeadlineAttentionCard|HeadlineCoverageCard|HeadlineScansCard>
 * }
 */
class BuildInfoHeadlineContract {

	use PluginControllerConsumer;

	/**
	 * @return InfoHeadlineContract
	 */
	public function build() :array {
		$overview = $this->buildOverviewQuery();
		$attention = $overview[ 'attention_summary' ] ?? [];
		$posture = $overview[ 'posture' ] ?? [];
		$scans = $overview[ 'scans' ] ?? [];

		$attentionTotal = (int)( $attention[ 'total' ] ?? 0 );
		$attentionState = $attentionTotal > 0 ? 'attention' : 'all_clear';

		return [
			'summary' => [
				'title'    => $attentionTotal > 0
					? $this->formatIssuesNeedAttention( $attentionTotal )
					: __( 'All clear right now', 'wp-simple-firewall' ),
				'subtitle' => $attentionTotal > 0
					? __( 'Current alert status across your site.', 'wp-simple-firewall' )
					: __( 'No current issues are waiting for action.', 'wp-simple-firewall' ),
				'state'    => $attentionState,
				'total_issues' => $attentionTotal,
			],
			'cards'   => [
				$this->buildAttentionCard( $attention ),
				$this->buildCoverageCard( $posture ),
				$this->buildScanCard( $scans ),
			],
		];
	}

	protected function buildOverviewQuery() :array {
		return self::con()->comps->site_query->overview();
	}

	protected function buildAttentionCard( array $attention ) :array {
		$total = \max( 0, (int)( $attention[ 'total' ] ?? 0 ) );
		$state = $total > 0 ? 'attention' : 'all_clear';

		return [
			'key'         => 'attention',
			'label'       => __( 'Alert Status', 'wp-simple-firewall' ),
			'value'       => $total > 0
				? $this->formatIssuesNeedAttention( $total )
				: __( 'All clear', 'wp-simple-firewall' ),
			'meta'        => $total > 0
				? __( 'Current issues requiring attention.', 'wp-simple-firewall' )
				: __( 'No current issues are waiting for action.', 'wp-simple-firewall' ),
			'state'       => $state,
			'severity'    => $state === 'attention'
				? $this->normalizeSeverity( (string)( $attention[ 'severity' ] ?? 'good' ) )
				: 'good',
			'total_issues' => $total,
		];
	}

	protected function buildCoverageCard( array $posture ) :array {
		$zones = $this->normalizeZoneCounts( \is_array( $posture[ 'zones' ] ?? null ) ? $posture[ 'zones' ] : [] );
		$percentage = \max( 0, \min( 100, (int)( $posture[ 'percentage' ] ?? 0 ) ) );

		return [
			'key'        => 'coverage',
			'label'      => __( 'Configuration Coverage', 'wp-simple-firewall' ),
			'value'      => \sprintf(
				__( '%s%% configured', 'wp-simple-firewall' ),
				$percentage
			),
			'meta'       => $this->buildCoverageMeta( [ 'zones' => $zones ] ),
			'severity'   => $this->normalizeSeverity( (string)( $posture[ 'severity' ] ?? 'good' ) ),
			'percentage' => $percentage,
			'zones'      => $zones,
		];
	}

	protected function buildScanCard( array $scans ) :array {
		$latestCompletedAt = (int)\max( \array_map(
			static fn( $timestamp ) :int => (int)$timestamp,
			$scans[ 'latest_completed_at' ] ?? [ 0 ]
		) );
		$isRunning = !empty( $scans[ 'is_running' ] );
		$enqueuedCount = (int)( $scans[ 'enqueued_count' ] ?? 0 );

		if ( $isRunning ) {
			return [
				'key'    => 'scans',
				'label'  => __( 'Scan Status', 'wp-simple-firewall' ),
				'value'  => __( 'Scans running', 'wp-simple-firewall' ),
				'meta'   => \sprintf(
					_n( '%s scan task queued', '%s scan tasks queued', $enqueuedCount, 'wp-simple-firewall' ),
					$enqueuedCount
				),
				'state'  => 'running',
				'enqueued_count' => \max( 0, $enqueuedCount ),
				'latest_completed_at' => $latestCompletedAt,
			];
		}

		if ( $latestCompletedAt > 0 ) {
			return [
				'key'    => 'scans',
				'label'  => __( 'Scan Status', 'wp-simple-firewall' ),
				'value'  => \sprintf(
					__( 'Last scan: %s', 'wp-simple-firewall' ),
					$this->formatTimestampDiff( $latestCompletedAt )
				),
				'meta'   => __( 'No scans are currently running.', 'wp-simple-firewall' ),
				'state'  => 'completed',
				'enqueued_count' => \max( 0, $enqueuedCount ),
				'latest_completed_at' => $latestCompletedAt,
			];
		}

		return [
			'key'    => 'scans',
			'label'  => __( 'Scan Status', 'wp-simple-firewall' ),
			'value'  => __( 'No completed scans recorded', 'wp-simple-firewall' ),
			'meta'   => __( 'Run a scan to refresh current site status.', 'wp-simple-firewall' ),
			'state'  => 'not_started',
			'enqueued_count' => \max( 0, $enqueuedCount ),
			'latest_completed_at' => 0,
		];
	}

	protected function formatTimestampDiff( int $timestamp ) :string {
		return \human_time_diff( $timestamp, $this->currentTimestamp() ).' '.__('ago', 'wp-simple-firewall');
	}

	protected function currentTimestamp() :int {
		return \time();
	}

	protected function buildCoverageMeta( array $posture ) :string {
		$zones = $this->normalizeZoneCounts( \is_array( $posture[ 'zones' ] ?? null ) ? $posture[ 'zones' ] : [] );
		$critical = $zones[ 'critical' ];
		$warning = $zones[ 'warning' ];
		$good = $zones[ 'good' ];

		return \sprintf(
			__( '%1$s, %2$s, %3$s', 'wp-simple-firewall' ),
			\sprintf( _n( '%s critical zone', '%s critical zones', $critical, 'wp-simple-firewall' ), $critical ),
			\sprintf( _n( '%s zone needs review', '%s zones need review', $warning, 'wp-simple-firewall' ), $warning ),
			\sprintf( _n( '%s zone ready', '%s zones ready', $good, 'wp-simple-firewall' ), $good )
		);
	}

	protected function formatIssuesNeedAttention( int $count ) :string {
		return \sprintf(
			_n( '%s issue needs attention', '%s issues need attention', $count, 'wp-simple-firewall' ),
			$count
		);
	}

	/**
	 * @return 'good'|'warning'|'critical'
	 */
	private function normalizeSeverity( string $severity ) :string {
		return \in_array( $severity, [ 'good', 'warning', 'critical' ], true ) ? $severity : 'good';
	}

	/**
	 * @param array<string,mixed> $zones
	 * @return HeadlineZones
	 */
	private function normalizeZoneCounts( array $zones ) :array {
		$counts = [
			'total'    => \max( 0, (int)( $zones[ 'total' ] ?? 0 ) ),
			'good'     => \max( 0, (int)( $zones[ 'good' ] ?? 0 ) ),
			'warning'  => \max( 0, (int)( $zones[ 'warning' ] ?? 0 ) ),
			'critical' => \max( 0, (int)( $zones[ 'critical' ] ?? 0 ) ),
		];

		if ( $counts[ 'total' ] === 0 ) {
			$counts[ 'total' ] = $counts[ 'good' ] + $counts[ 'warning' ] + $counts[ 'critical' ];
		}

		return $counts;
	}
}
