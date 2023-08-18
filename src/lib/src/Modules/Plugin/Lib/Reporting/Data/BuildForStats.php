<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

class BuildForStats {

	use ModConsumer;

	private $events;

	private $startAt;

	private $endAt;

	public function __construct( int $start, int $end, array $events = [] ) {
		$this->startAt = $start;
		$this->endAt = $end;
		$this->events = $events;
	}

	public function build() :array {
		$statData = [];
		foreach ( empty( $this->events ) ? $this->getDefaultEventsToStat() : $this->events as $event ) {
			$statData[ $event ] = $this->buildForEvent( $event );
		}
		return \array_filter( $statData );
	}

	public function buildForEvent( string $event ) :?array {
		$countData = null;

		/** @var DBEvents\Select $selector */
		$selector = self::con()->getModule_Events()->getDbHandler_Events()->getQuerySelector();
		try {
			$intervalDiff = $this->endAt - $this->startAt;
			$eventSumLatest = $selector
				->filterByBoundary( $this->startAt, $this->endAt )
				->sumEvent( $event );
			$eventSumPrevious = $selector
				->filterByBoundary( $this->startAt - $intervalDiff, $this->startAt )
				->sumEvent( $event );
			// TODO: Configurable whether we include ZERO stat events
			if ( $eventSumLatest > 0 || $eventSumPrevious > 0 ) {
				$diff = $eventSumLatest - $eventSumPrevious;
				$countData = [
					'count_latest'   => $eventSumLatest,
					'count_previous' => $eventSumPrevious,
					'count_diff'     => \abs( $diff ),
					'diff_symbol'    => $diff > 0 ? '↗' : ( $diff < 0 ? '↘' : '➡' ),
					'name'           => self::con()->loadEventsService()->getEventName( $event ),
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