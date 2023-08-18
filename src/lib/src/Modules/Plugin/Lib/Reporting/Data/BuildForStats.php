<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

class BuildForStats {

	use ModConsumer;

	private $startAt;

	private $endAt;

	public function __construct( int $start, int $end ) {
		$this->startAt = $start;
		$this->endAt = $end;
	}

	public function build( array $events = [] ) :array {
		$statData = [];
		foreach ( empty( $events ) ? $this->getDefaultEventsToStat() : $events as $event ) {
			$statData[ $event ] = $this->buildForEvent( $event );
		}
		return \array_filter( $statData );
	}

	public function buildForEvent( string $event ) :?array {
		$con = self::con();
		$countData = null;

		/** @var DBEvents\Select $selector */
		$selector = $con->getModule_Events()->getDbHandler_Events()->getQuerySelector();
		try {
			$sumCurrent = $selector
				->filterByBoundary( $this->startAt, $this->endAt )
				->sumEvent( $event );
			$sumPrevious = $this->startAt === 0 ? 0
				: $selector->filterByBoundary( $this->startAt - ( $this->endAt - $this->startAt ), $this->startAt )
						   ->sumEvent( $event );

			// TODO: Configurable whether we include ZERO stat events
			if ( $sumCurrent > 0 || $sumPrevious > 0 ) {
				$diff = $sumCurrent - $sumPrevious;
				$countData = [
					'count_current_period'   => $sumCurrent,
					'count_previous_period'  => $sumPrevious,
					'count_diff'             => $diff,
					'count_diff_abs'         => \abs( $diff ),
					'diff_symbol_email'      => $diff > 0 ? '↗' : ( $diff < 0 ? '↘' : '➡' ),
					'diff_symbol_svg'        => $con->svgs->raw( $diff > 0 ? 'arrow-up-right' : ( $diff < 0 ? 'arrow-down-right' : 'arrow-right' ) ),
					'diff_symbol_plus_minus' => $diff > 0 ? '+' : ( $diff < 0 ? '-' : '' ),
					'diff_colour'            => $diff > 0 ? 'warning' : ( $diff < 0 ? 'success' : 'info' ),
					'name'                   => $con->loadEventsService()->getEventName( $event ),
				];
			}
		}
		catch ( \Exception $e ) {
		}

		return $countData;
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