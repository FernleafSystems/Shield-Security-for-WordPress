<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\DB\Event\Ops as EventsDB;

class BuildForStats extends BuildBase {

	public function build( array $events = [] ) :array {
		$con = self::con();
		$data = [];
		if ( empty( $events ) ) {
			$events = $this->getDefaultEventsToStat();
		}

		/** @var EventsDB\Select $selector */
		$selector = $con->getModule_Events()->getDbH_Events()->getQuerySelector();
		$countsCurrent = $selector
			->filterByBoundary( $this->start, $this->end )
			->sumEventsSeparately( $events );
		$countsPrevious = $this->start === 0 ?
			\array_fill_keys( $events, 0 )
			: $selector->filterByBoundary( $this->start - ( $this->end - $this->start ), $this->start )
					   ->sumEventsSeparately( $events );

		foreach ( $events as $event ) {
			$sumCurrent = $countsCurrent[ $event ];
			$sumPrevious = $countsPrevious[ $event ];
			$diff = $sumCurrent - $sumPrevious;
			$data[ $event ] = [
				'name'                   => $con->loadEventsService()->getEventName( $event ),
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

	public function buildForEvent( string $event ) :?array {
		return $this->build( [ $event ] )[ $event ];
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
			'spam_block_recaptcha',
			'spam_block_human',
		];
	}
}