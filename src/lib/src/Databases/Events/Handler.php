<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Handler extends Base\Handler {

	/**
	 * @param $events - array of events: key event slug, value created_at timestamp
	 */
	public function commitEvents( array $events ) {
		foreach ( $events as $event => $count ) {
			$this->commitEvent( $event, $count );
		}
	}

	/**
	 * @param string $evt
	 * @param int    $count
	 * @return bool
	 */
	public function commitEvent( string $evt, int $count = 1 ) {
		/** @var EntryVO $entry */
		$entry = $this->getVo();
		$entry->event = $evt;
		$entry->count = max( 1, $count );
		$entry->created_at = Services::Request()->ts();
		/** @var Insert $QI */
		$QI = $this->getQueryInserter();
		return $QI->insert( $entry );
	}
}