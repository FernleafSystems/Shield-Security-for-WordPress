<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Event\Ops;

use FernleafSystems\Wordpress\Services\Services;

class Handler extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler {

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