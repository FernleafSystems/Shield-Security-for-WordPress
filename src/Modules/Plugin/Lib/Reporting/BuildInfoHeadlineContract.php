<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class BuildInfoHeadlineContract {

	use PluginControllerConsumer;

	public function build() :array {
		$overview = $this->buildOverviewQuery();
		$attention = $overview[ 'attention_summary' ] ?? [];
		$posture = $overview[ 'posture' ] ?? [];
		$scans = $overview[ 'scans' ] ?? [];

		$attentionTotal = (int)( $attention[ 'total' ] ?? 0 );

		return [
			'summary' => [
				'title'    => $attentionTotal > 0
					? $this->formatIssuesNeedAttention( $attentionTotal )
					: __( 'All clear right now', 'wp-simple-firewall' ),
				'subtitle' => $attentionTotal > 0
					? __( 'Current alert status across your site.', 'wp-simple-firewall' )
					: __( 'No current critical issues are waiting for action.', 'wp-simple-firewall' ),
			],
			'cards'   => [
				[
					'label'  => __( 'Alert Status', 'wp-simple-firewall' ),
					'value'  => $attentionTotal > 0
						? $this->formatIssuesNeedAttention( $attentionTotal )
						: __( 'All clear', 'wp-simple-firewall' ),
					'meta'   => $attentionTotal > 0
						? __( 'Current critical issues requiring attention.', 'wp-simple-firewall' )
						: __( 'No current critical issues are waiting for action.', 'wp-simple-firewall' ),
				],
				[
					'label'  => __( 'Security Posture', 'wp-simple-firewall' ),
					'value'  => \sprintf(
						__( '%s%% configured', 'wp-simple-firewall' ),
						(int)( $posture[ 'percentage' ] ?? 0 )
					),
					'meta'   => \sprintf(
						__( 'Current posture grade: %s', 'wp-simple-firewall' ),
						(string)( $posture[ 'totals' ][ 'letter_score' ] ?? '?' )
					),
				],
				$this->buildScanCard( $scans ),
			],
		];
	}

	protected function buildOverviewQuery() :array {
		return self::con()->comps->site_query->overview();
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
				'label'  => __( 'Scan Status', 'wp-simple-firewall' ),
				'value'  => __( 'Scans running', 'wp-simple-firewall' ),
				'meta'   => \sprintf(
					_n( '%s scan task queued', '%s scan tasks queued', $enqueuedCount, 'wp-simple-firewall' ),
					$enqueuedCount
				),
			];
		}

		if ( $latestCompletedAt > 0 ) {
			return [
				'label'  => __( 'Scan Status', 'wp-simple-firewall' ),
				'value'  => \sprintf(
					__( 'Last scan: %s', 'wp-simple-firewall' ),
					$this->formatTimestampDiff( $latestCompletedAt )
				),
				'meta'   => __( 'No scans are currently running.', 'wp-simple-firewall' ),
			];
		}

		return [
			'label'  => __( 'Scan Status', 'wp-simple-firewall' ),
			'value'  => __( 'No completed scans recorded', 'wp-simple-firewall' ),
			'meta'   => __( 'Run a scan to refresh current site status.', 'wp-simple-firewall' ),
		];
	}

	protected function formatTimestampDiff( int $timestamp ) :string {
		return \human_time_diff( $timestamp, $this->currentTimestamp() ).' '.__('ago', 'wp-simple-firewall');
	}

	protected function currentTimestamp() :int {
		return \time();
	}

	protected function formatIssuesNeedAttention( int $count ) :string {
		return \sprintf(
			_n( '%s issue needs attention', '%s issues need attention', $count, 'wp-simple-firewall' ),
			$count
		);
	}
}
