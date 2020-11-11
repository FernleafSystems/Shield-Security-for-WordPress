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
	 * @param array  $aMeta
	 */
	protected function captureEvent( $evt, $aMeta = [] ) {
		$aStats = $this->getEventStats();
		$aStats[ $evt ] = isset( $aMeta[ 'ts' ] ) ? $aMeta[ 'ts' ] : Services::Request()->ts();
		$this->setEventStats( $aStats );
	}

	protected function onShutdown() {
		if ( !$this->getCon()->plugin_deleting ) {
			/** @var Handler $oDbH */
			$oDbH = $this->getDbHandler();
			$oDbH->commitEvents( $this->getEventStats() );
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