<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Events\EventsParser;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;

class BuildForStats extends BuildBase {

	/**
	 * TODO: it currently builds all stats and then filters. Probably best to optimise.
	 */
	public function build() :array {
		$eventsParser = new EventsParser();
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
					'stats'   => $this->buildForGroup( \array_keys( $eventsParser->security() ) ),
					'neutral' => false,
				],
				'wordpress'     => [
					'title'   => __( 'WordPress Stats', 'wp-simple-firewall' ),
					'stats'   => $this->buildForGroup( \array_keys( $eventsParser->wordpress() ) ),
					'neutral' => true,
				],
				'user_accounts' => [
					'title'   => __( 'User Accounts', 'wp-simple-firewall' ),
					'stats'   => $this->buildForGroup( \array_keys( $eventsParser->accounts() ) ),
					'neutral' => true,
				],
				'user_access'   => [
					'title'   => __( 'User Access', 'wp-simple-firewall' ),
					'stats'   => $this->buildForGroup( \array_keys( $eventsParser->userAccess() ) ),
					'neutral' => true,
				],
			]
		);

		return \array_intersect_key( $rawStats, \array_flip( $this->report->areas[ Constants::REPORT_AREA_STATS ] ) );
	}

	public function buildForGroup( array $eventsGroup = [] ) :array {
		$con = self::con();
		$data = [];
		if ( empty( $eventsGroup ) ) {
			$eventsGroup = $this->getDefaultEventsToStat();
		}

		$start = $this->report->start_at;
		$end = $this->report->end_at;

		/** @var EventsDB\Select $selector */
		$selector = $con->db_con->events->getQuerySelector();
		$countsCurrent = $selector
			->filterByBoundary( $start, $end )
			->sumEventsSeparately( $eventsGroup );
		$countsPrevious = $start === 0 ?
			\array_fill_keys( $eventsGroup, 0 )
			: $selector->reset()
					   ->filterByBoundary( $start - ( $end - $start ), $start )
					   ->sumEventsSeparately( $eventsGroup );

		foreach ( $eventsGroup as $event ) {
			$sumCurrent = $countsCurrent[ $event ];
			$sumPrevious = $countsPrevious[ $event ];
			$diff = $sumCurrent - $sumPrevious;
			$data[ $event ] = [
				'name'                   => $con->comps->events->getEventName( $event ),
				'count_current_period'   => $sumCurrent,
				'count_previous_period'  => $sumPrevious,
				'is_zero_stat'           => empty( $sumCurrent ) && empty( $sumPrevious ),
				'count_diff'             => $diff,
				'count_diff_abs'         => \abs( $diff ),
				'diff_symbol_email'      => $diff > 0 ? '↗' : ( $diff < 0 ? '↘' : '➡' ),
				'diff_symbol_svg'        => $con->svgs->raw( $diff > 0 ? 'arrow-up-right' : ( $diff < 0 ? 'arrow-down-right' : 'arrow-right' ) ),
				'diff_symbol_plus_minus' => $diff > 0 ? '+' : ( $diff < 0 ? '-' : '' ),
				'diff_colour'            => $diff > 0 ? 'warning' : ( $diff < 0 ? 'success' : 'info' ),
			];
		}

		return $data;
	}

	private function getDefaultEventsToStat() :array {
		return [
			'ip_offense',
			'ip_blocked',
			'conn_kill',
			'firewall_block',
			'bottrack_404',
			'bottrack_fakewebcrawler',
			'bottrack_linkcheese',
			'bottrack_loginfailed',
			'bottrack_logininvalid',
			'bottrack_xmlrpc',
			'bottrack_invalidscript',
			'spam_block_bot',
			'spam_block_human',
		];
	}
}