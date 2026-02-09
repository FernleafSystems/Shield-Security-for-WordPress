<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Events;

class StatsWriter extends EventsListener {

	/**
	 * @var int[] - key: event; value: count
	 */
	private array $stats = [];

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

	/**
	 * TODO: consider self::con()->is_my_upgrade - potential to skip events during our own upgrades.
	 */
	public function isCommit() :bool {
		$con = self::con();
		return !empty( $this->stats ) && !$con->is_my_upgrade && $con->db_con->events->isReady();
	}
}