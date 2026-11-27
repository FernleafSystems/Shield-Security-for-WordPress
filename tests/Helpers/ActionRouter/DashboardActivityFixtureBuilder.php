<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Services\Services;

/**
 * One offence recorded just now, so the dashboard has an activity stat to show: a site where nothing has happened
 * shows none. The record is removed afterwards.
 * @phpstan-type FixtureState array{event_id:int}
 */
class DashboardActivityFixtureBuilder {

	/**
	 * @return array{contract:array<string,mixed>,state:FixtureState}
	 */
	public function seed() :array {
		$dbh = RuntimeTestState::controller()->db_con->events;
		$record = $dbh->getRecord();
		$record->event = 'ip_offense';
		$record->count = 1;
		$record->created_at = \time();
		$dbh->getQueryInserter()->insert( $record );

		return [
			'contract' => [],
			'state'    => [ 'event_id' => (int)Services::WpDb()->loadWpdb()->insert_id ],
		];
	}

	/**
	 * @param array<string,mixed> $state
	 */
	public function cleanup( array $state ) :void {
		$eventId = (int)( $state[ 'event_id' ] ?? 0 );
		if ( $eventId > 0 ) {
			RuntimeTestState::controller()->db_con->events->getQueryDeleter()->deleteById( $eventId );
		}
	}
}
