<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events\Handler;
use FernleafSystems\Wordpress\Services\Services;

class StatsWriter extends EventsListener {

	use HandlerConsumer;

	/**
	 * @var int[] - key: event; value: timestamp
	 */
	private $aEventStats;

	/**
	 * @param string $evt
	 * @param array  $meta
	 * @param array  $def
	 */
	protected function captureEvent( string $evt, $meta = [], $def = [] ) {
		if ( empty( $def ) ) {
			$def = $this->getCon()->loadEventsService()->getEventDef( $evt );
		}
		if ( !empty( $def[ 'stat' ] ) ) {
			$stats = $this->getEventStats();
			$stats[ $evt ] = $meta[ 'ts' ] ?? Services::Request()->ts();
			$this->setEventStats( $stats );
		}
	}

	protected function onShutdown() {
		if ( !$this->getCon()->plugin_deleting ) {
			/** @var Handler $dbh */
			$dbh = $this->getDbHandler();
			$dbh->commitEvents( $this->getEventStats() );
			$this->setEventStats();
		}
	}

	/**
	 * @return int[]
	 */
	public function getEventStats() {
		return is_array( $this->aEventStats ) ? $this->aEventStats : [];
	}

	/**
	 * @param int[] $aStats
	 * @return $this
	 */
	public function setEventStats( $aStats = [] ) {
		$this->aEventStats = $aStats;
		return $this;
	}
}