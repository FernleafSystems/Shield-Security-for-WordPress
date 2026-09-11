<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Events;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Time\CalendarIntervalWindowResolver;

class ConsolidateAllEvents {

	use PluginControllerConsumer;

	public const CURSOR_OPTION = 'events_compaction_cursor';
	public const GUARD_TRANSIENT = 'events_compaction_guard';

	private const GUARD_TTL = 600;
	private const RECENT_DAILY_BUCKETS = 14;
	private const HISTORICAL_BUCKET_BUDGET = 14;

	public function run( Carbon $referenceNow ) :bool {
		$referenceNow = ( clone $referenceNow )->setTimezone( \wp_timezone() );
		$guardToken = $this->acquireGuard();
		if ( $guardToken === '' ) {
			return true;
		}

		try {
			return $this->compactDailyWindows( $referenceNow );
		}
		finally {
			$this->releaseGuard( $guardToken );
		}
	}

	private function compactDailyWindows( Carbon $referenceNow ) :bool {
		$resolver = new CalendarIntervalWindowResolver();
		$recentStart = 0;
		for ( $offset = 0; $offset < self::RECENT_DAILY_BUCKETS; $offset++ ) {
			$window = $resolver->resolveWindowContaining(
				'daily',
				( clone $referenceNow )->subDays( $offset )
			);
			if ( !$this->eventsHandler()->compactBoundary( $window->start_at, $window->end_at ) ) {
				return false;
			}
			$recentStart = $window->start_at;
		}

		return $this->compactHistoricalDays( $recentStart, $resolver );
	}

	private function compactHistoricalDays( int $recentStart, CalendarIntervalWindowResolver $resolver ) :bool {
		$cursorKey = self::con()->prefix( self::CURSOR_OPTION );
		$cursor = (int)\get_option( $cursorKey, 0 );
		if ( $cursor < 0 || $cursor >= $recentStart ) {
			$cursor = 0;
		}

		$lastSuccessfulCursor = $cursor;
		for ( $processed = 0; $processed < self::HISTORICAL_BUCKET_BUDGET; $processed++ ) {
			try {
				$nextCreatedAt = $this->eventsHandler()->getNextCreatedAt( $lastSuccessfulCursor, $recentStart );
			}
			catch ( \RuntimeException $e ) {
				$this->storeCursor( $cursorKey, $lastSuccessfulCursor );
				return false;
			}
			if ( $nextCreatedAt === null ) {
				$lastSuccessfulCursor = $recentStart - 1;
				break;
			}

			$dayWindow = $resolver->resolveWindowContaining(
				'daily',
				Carbon::createFromTimestamp( $nextCreatedAt, \wp_timezone() )
			);
			if ( !$this->eventsHandler()->compactBoundary( $dayWindow->start_at, $dayWindow->end_at ) ) {
				$this->storeCursor( $cursorKey, $lastSuccessfulCursor );
				return false;
			}
			$lastSuccessfulCursor = $dayWindow->end_at;
		}

		$this->storeCursor( $cursorKey, $lastSuccessfulCursor );
		return true;
	}

	private function storeCursor( string $cursorKey, int $cursor ) :void {
		if ( !\add_option( $cursorKey, $cursor, '', false ) ) {
			\update_option( $cursorKey, $cursor, false );
		}
	}

	private function acquireGuard() :string {
		$key = $this->guardKey();
		if ( \get_transient( $key ) !== false ) {
			return '';
		}

		$token = \wp_generate_uuid4();
		if ( !\set_transient( $key, $token, self::GUARD_TTL ) ) {
			return '';
		}

		return \get_transient( $key ) === $token ? $token : '';
	}

	private function releaseGuard( string $token ) :void {
		$key = $this->guardKey();
		if ( \hash_equals( $token, (string)\get_transient( $key ) ) ) {
			\delete_transient( $key );
		}
	}

	private function guardKey() :string {
		return self::con()->prefix( self::GUARD_TRANSIENT );
	}

	private function eventsHandler() :Handler {
		return self::con()->db_con->events;
	}
}
