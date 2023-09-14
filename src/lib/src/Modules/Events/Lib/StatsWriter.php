<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib;

class StatsWriter extends EventsListener {

	/**
	 * @var int[] - key: event; value: timestamp
	 */
	private $aEventStats;

	protected function captureEvent( string $evt, array $meta = [], array $def = [] ) {
		if ( !empty( $def[ 'stat' ] ) ) {
			$stats = $this->getEventStats();
			if ( !isset( $stats[ $evt ] ) ) {
				$stats[ $evt ] = 0;
			}
			$stats[ $evt ]++;
			$this->setEventStats( $stats );
		}
	}

	protected function onShutdown() {
		if ( !self::con()->plugin_deleting ) {
			$mod = self::con()->getModule_Events();
			$mod->getDbH_Events()->commitEvents( $this->getEventStats() );
			$this->setEventStats();
		}
	}

	/**
	 * @return int[]
	 */
	public function getEventStats() :array {
		return \is_array( $this->aEventStats ) ? $this->aEventStats : [];
	}

	/**
	 * @param int[] $stats
	 * @return $this
	 */
	public function setEventStats( array $stats = [] ) {
		$this->aEventStats = $stats;
		return $this;
	}
}