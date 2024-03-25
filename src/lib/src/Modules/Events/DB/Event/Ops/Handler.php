<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\DB\Event\Ops;

use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 19.1
 */
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
		$entry = $this->getRecord();
		$entry->event = $evt;
		$entry->count = \max( 1, $count );
		$entry->created_at = Services::Request()->ts();
		$QI = $this->getQueryInserter();
		return $QI->insert( $entry );
	}
}