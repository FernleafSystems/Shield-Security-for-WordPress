<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Events\EventsParser;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;

class BuildForStats extends BuildBase {

	public function build() :array {
		$eventsParser = new EventsParser();
		$definitions = [
			'security'      => [ __( 'Security Stats', 'wp-simple-firewall' ), $eventsParser->security(), false ],
			'wordpress'     => [ __( 'WordPress Stats', 'wp-simple-firewall' ), $eventsParser->wordpress(), true ],
			'user_accounts' => [ __( 'User Accounts', 'wp-simple-firewall' ), $eventsParser->accounts(), true ],
			'user_access'   => [ __( 'User Access', 'wp-simple-firewall' ), $eventsParser->userAccess(), true ],
		];
		$requested = \array_flip( $this->report->areas[ Constants::REPORT_AREA_STATS ] );
		$stats = [];
		foreach ( \array_intersect_key( $definitions, $requested ) as $key => [ $title, $eventDefinitions, $neutral ] ) {
			$groupStats = $this->buildForGroup( \array_keys( $eventDefinitions ) );
			$stats[ $key ] = [
				'title'             => $title,
				'stats'             => $groupStats,
				'has_non_zero_stat' => \count( \array_filter(
					$groupStats,
					static fn( array $stat ) :bool => !$stat[ 'is_zero_stat' ]
				) ) > 0,
				'neutral'           => $neutral,
			];
		}

		return $stats;
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
		$countsPrevious = $selector->reset()
						   ->filterByBoundary( $this->report->previous_start_at, $this->report->previous_end_at )
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
				'diff_symbol_icon_class' => $con->svgs->iconClass( $diff > 0 ? 'arrow-up-right' : ( $diff < 0 ? 'arrow-down-right' : 'arrow-right' ) ),
				'diff_symbol_plus_minus' => $diff > 0 ? '+' : ( $diff < 0 ? '-' : '' ),
				'diff_colour'            => $diff > 0 ? 'warning' : ( $diff < 0 ? 'success' : 'info' ),
				'diff_percentage'        => self::calcDiffPercentage( $sumCurrent, $sumPrevious ),
			];
		}

		return $data;
	}

	public static function calcDiffPercentage( int $sumCurrent, int $sumPrevious ) :int {
		$diff = $sumCurrent - $sumPrevious;
		return $sumPrevious > 0
			? (int)\round( ( $diff / $sumPrevious ) * 100 )
			: ( $sumCurrent > 0 ? 100 : 0 );
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
