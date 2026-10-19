<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops as EventsDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Time\CalendarIntervalWindowResolver;

/**
 * The daily security totals ShieldCentral asks for on each sync: for each full day in the site's time zone, from the
 * day Central asks from through yesterday, the count of each listed event above zero.
 */
class BuildDailyTotals {

	use PluginControllerConsumer;

	/**
	 * The events ShieldCentral reports, and the only ones it accepts. ip_offense is left out as it records a request
	 * firewall_block or conn_kill already counted, and comment_spam_block as it isn't kept in the daily statistics.
	 */
	public const EVENTS = [
		'firewall_block',
		'conn_kill',
		'ip_blocked',
		'bottrack_404',
		'bottrack_fakewebcrawler',
		'bottrack_linkcheese',
		'bottrack_xmlrpc',
		'bottrack_invalidscript',
		'bottrack_loginfailed',
		'bottrack_logininvalid',
		'2fa_verify_fail',
		'spam_block_antibot',
		'spam_block_human',
		'spam_block_humanrepeated',
		'spam_block_cooldown',
		'user_login',
		'user_registered',
	];

	/**
	 * The furthest back Central can ask: a year, with room for the difference between the site's days and Central's
	 * UTC days.
	 */
	public const MAX_DAYS_BACK = 400;

	/**
	 * How far back the totals go when Central sends no date.
	 */
	public const DEFAULT_DAYS_BACK = 365;

	/**
	 * Null when the counts couldn't be read, so the sync leaves the totals out and Central asks for the same days again.
	 * @return array{from:string, through:string, days:array<string,array<string,int>>}|null
	 */
	public function build( ?string $since, Carbon $now ) :?array {
		$today = ( clone $now )->setTimezone( \wp_timezone() )->startOfDay();
		$through = ( clone $today )->subDay();
		$asked = $this->askedFrom( $since, $today, $through );

		$resolver = new CalendarIntervalWindowResolver();
		$dates = [];
		$windowStarts = [];
		// Each step goes back to the start of the day, as in zones whose clocks jump at midnight a day can start at 01:00.
		for ( $day = clone $asked; $day->toDateString() <= $through->toDateString(); $day->addDay()->startOfDay() ) {
			$dates[] = $day->toDateString();
			$windowStarts[] = $resolver->resolveWindowContaining( 'daily', $day )->start_at;
		}

		try {
			$totals = $this->selector()->sumEventsPerWindow(
				self::EVENTS,
				$windowStarts,
				$resolver->resolveWindowContaining( 'daily', $through )->end_at
			);
			$from = $this->firstDayWithData( $asked );
		}
		catch ( \RuntimeException $e ) {
			return null;
		}

		$days = [];
		foreach ( $totals as $windowIndex => $eventTotals ) {
			$counts = \array_filter( $eventTotals, fn( int $total ) :bool => $total > 0 );
			if ( !empty( $counts ) ) {
				$days[ $dates[ $windowIndex ] ] = $counts;
			}
		}
		\ksort( $days );

		return [
			'from'    => $from->toDateString(),
			'through' => $through->toDateString(),
			'days'    => $days,
		];
	}

	/**
	 * The day Central asked from, within MAX_DAYS_BACK and yesterday, or DEFAULT_DAYS_BACK when it sent no valid date.
	 */
	private function askedFrom( ?string $since, Carbon $today, Carbon $through ) :Carbon {
		$asked = null;
		if ( \is_string( $since ) && \preg_match( '/^\d{4}-\d{2}-\d{2}$/', $since ) === 1 ) {
			$parsed = Carbon::createFromFormat( '!Y-m-d', $since, \wp_timezone() );
			$asked = $parsed instanceof Carbon && $parsed->toDateString() === $since ? $parsed : null;
		}
		$asked ??= ( clone $today )->subDays( self::DEFAULT_DAYS_BACK );

		return $asked->max( ( clone $today )->subDays( self::MAX_DAYS_BACK ) )->min( $through );
	}

	/**
	 * The later of the day asked from and the oldest day the events table holds; the day asked from when it holds none.
	 * @throws \RuntimeException when the oldest event can't be read
	 */
	private function firstDayWithData( Carbon $asked ) :Carbon {
		$oldest = $this->selector()->getOldestCreatedAt();
		if ( $oldest === null ) {
			return $asked;
		}

		return $asked->max( Carbon::createFromTimestamp( $oldest, \wp_timezone() )->startOfDay() );
	}

	private function selector() :EventsDB\Select {
		/** @var EventsDB\Select $selector */
		$selector = self::con()->db_con->events->getQuerySelector();
		return $selector;
	}
}