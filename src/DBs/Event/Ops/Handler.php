<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops;

use FernleafSystems\Wordpress\Services\Services;

class Handler extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler {

	public function compactBoundary( int $startAt, int $endAt ) :bool {
		if ( $startAt < 0 || $endAt < $startAt ) {
			throw new \InvalidArgumentException( 'Invalid event compaction boundary.' );
		}

		$wpdb = Services::WpDb()->loadWpdb();
		$table = $this->getTableSchema()->table;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT `event`, COUNT(*) AS `record_count`, MAX(`id`) AS `captured_max_id`,
				MAX(`created_at`) AS `latest_at`, SUM(`count`) AS `event_total`
			FROM `{$table}`
			WHERE `created_at` >= %d AND `created_at` <= %d
			GROUP BY `event`",
			$startAt,
			$endAt
		), ARRAY_A );

		if ( !\is_array( $rows ) || $wpdb->last_error !== '' ) {
			return false;
		}

		foreach ( $rows as $row ) {
			if ( (int)( $row[ 'record_count' ] ?? 0 ) < 2 ) {
				continue;
			}

			$event = (string)( $row[ 'event' ] ?? '' );
			$capturedMaxID = (int)( $row[ 'captured_max_id' ] ?? 0 );
			$latestAt = (int)( $row[ 'latest_at' ] ?? 0 );
			$total = (int)( $row[ 'event_total' ] ?? 0 );
			if ( $event === '' || $capturedMaxID < 1 || $latestAt < $startAt || $latestAt > $endAt ) {
				return false;
			}

			$anchorID = $wpdb->get_var( $wpdb->prepare(
				"SELECT MAX(`id`) FROM `{$table}`
				WHERE `event` = %s AND `created_at` = %d AND `id` <= %d",
				$event,
				$latestAt,
				$capturedMaxID
			) );
			if ( $anchorID === null ) {
				return false;
			}
			$anchorID = (int)$anchorID;

			$updated = $wpdb->query( $wpdb->prepare(
				"UPDATE `{$table}`
				SET `count` = CASE WHEN `id` = %d THEN %d ELSE 0 END
				WHERE `event` = %s AND `created_at` >= %d AND `created_at` <= %d AND `id` <= %d",
				$anchorID,
				$total,
				$event,
				$startAt,
				$endAt,
				$capturedMaxID
			) );
			if ( $updated === false ) {
				return false;
			}

			$deleted = $wpdb->query( $wpdb->prepare(
				"DELETE FROM `{$table}`
				WHERE `event` = %s AND `created_at` >= %d AND `created_at` <= %d
				AND `id` <= %d AND `id` <> %d AND `count` = 0",
				$event,
				$startAt,
				$endAt,
				$capturedMaxID,
				$anchorID
			) );
			if ( $deleted === false ) {
				return false;
			}
		}

		return true;
	}

	public function getNextCreatedAt( int $afterAt, int $beforeAt ) :?int {
		$wpdb = Services::WpDb()->loadWpdb();
		$table = $this->getTableSchema()->table;
		$next = $wpdb->get_var( $wpdb->prepare(
			"SELECT MIN(`created_at`) FROM `{$table}` WHERE `created_at` > %d AND `created_at` < %d",
			$afterAt,
			$beforeAt
		) );
		if ( $wpdb->last_error !== '' ) {
			throw new \RuntimeException( 'Failed to query the next event compaction timestamp.' );
		}

		return $next === null ? null : (int)$next;
	}

	/**
	 * @param $events - array of events: key event slug, value created_at timestamp
	 */
	public function commitEvents( array $events ) {
		foreach ( $events as $event => $count ) {
			$this->commitEvent( $event, $count );
		}
	}

	public function commitEvent( string $evt, int $count = 1 ) :bool {
		/** @var Record $entry */
		$entry = $this->getRecord();
		$entry->event = $evt;
		$entry->count = \max( 1, $count );
		$entry->created_at = Services::Request()->ts();
		/** @var Insert $QI */
		$QI = $this->getQueryInserter();
		return $QI->insert( $entry );
	}
}
