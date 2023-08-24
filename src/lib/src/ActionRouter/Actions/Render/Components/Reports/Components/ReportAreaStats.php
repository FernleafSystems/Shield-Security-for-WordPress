<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\EventsParser;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data\BuildForStats;

class ReportAreaStats extends ReportAreaBase {

	public const SLUG = 'report_area_stats';
	public const TEMPLATE = '/reports/areas/stats/main.twig';

	protected function getRenderData() :array {
		$report = $this->report();
		$eventsParser = new EventsParser();
		$statsBuilder = new BuildForStats( $report->interval_start_at, $report->interval_end_at );
		$rawStats = \array_map(
			function ( array $data ) {
				$data[ 'stats_count' ] = \count( $data[ 'stats' ] );
				$data[ 'has_non_zero_stat' ] = \count( \array_filter( $data[ 'stats' ], function ( array $stat ) {
						return !$stat[ 'is_zero_stat' ];
					} ) ) > 0;
				return $data;
			},
			[
				'security'      => [
					'title'   => __( 'Security Stats', 'wp-simple-firewall' ),
					'stats'   => $statsBuilder->build( \array_keys( $eventsParser->security() ) ),
					'neutral' => false,
				],
				'wordpress'     => [
					'title'   => __( 'WordPress Stats', 'wp-simple-firewall' ),
					'stats'   => $statsBuilder->build( \array_keys( $eventsParser->wordpress() ) ),
					'neutral' => true,
				],
				'user_accounts' => [
					'title'   => __( 'User Accounts', 'wp-simple-firewall' ),
					'stats'   => $statsBuilder->build( \array_keys( $eventsParser->accounts() ) ),
					'neutral' => true,
				],
				'user_access'   => [
					'title'   => __( 'User Access', 'wp-simple-firewall' ),
					'stats'   => $statsBuilder->build( \array_keys( $eventsParser->userAccess() ) ),
					'neutral' => true,
				],
			]
		);

		$stats = \array_intersect_key( $rawStats, \array_flip( $report->areas[ 'statistics' ] ) );

		return [
			'flags' => [
				'has_stats' => !empty( $stats ),
			],
			'vars'  => [
				'stats' => $stats,
			],
		];
	}
}