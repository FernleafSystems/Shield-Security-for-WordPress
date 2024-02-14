<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Events;

class StatsWriter extends EventsListener {

	/**
	 * @var int[] - key: event; value: count
	 * @deprecated 19.1
	 */
	private $aEventStats;

	private $stats = [];

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
		if ( $this->isCommit() ) {
			self::con()->db_con->dbhEvents()->commitEvents( $this->getEventStats() );
			$this->setEventStats();
		}
	}

	public function isCommit() :bool {
		$con = self::con();
		return !$con->plugin_deleting && $con->db_con->dbhEvents()->isReady();
	}

	/**
	 * @return int[]
	 */
	public function getEventStats() :array {
		return \property_exists( $this, 'stats' ) ? $this->stats :
			( \is_array( $this->aEventStats ) ? $this->aEventStats : [] );
	}

	/**
	 * @param int[] $stats
	 */
	public function setEventStats( array $stats = [] ) {
		if ( \property_exists( $this, 'stats' ) ) {
			$this->stats = [];
		}
		$this->aEventStats = $stats;
	}
}