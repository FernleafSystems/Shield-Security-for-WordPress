<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Events;

class StatsWriter extends EventsListener {

	/**
	 * @var int[] - key: event; value: count
	 */
	private $stats = [];

	protected function captureEvent( string $evt, array $meta = [], array $def = [] ) {
		if ( !empty( $def[ 'stat' ] ) ) {
			$this->stats[ $evt ] = 1 + ( $this->stats[ $evt ] ?? 0 );
		}
	}

	protected function onShutdown() {
		if ( $this->isCommit() ) {
			self::con()->db_con->events->commitEvents( $this->stats );
			$this->stats = [];
		}
	}

	public function isCommit() :bool {
		$con = self::con();
		return !empty( $this->stats ) && !$con->plugin_deleting && $con->db_con->events->isReady();
	}
}