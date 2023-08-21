<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events\Handler;

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
		if ( !$this->con()->plugin_deleting ) {
			$mod = self::con()->getModule_Events();
			/** @var Handler $dbh */
			$dbh = \method_exists( $mod, 'getDbH_Events' ) ? $mod->getDbH_Events() : $mod->getDbHandler_Events();
			$dbh->commitEvents( $this->getEventStats() );
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