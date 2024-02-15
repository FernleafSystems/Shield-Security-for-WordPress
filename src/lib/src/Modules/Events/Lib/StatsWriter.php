<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib;

/**
 * @deprecated 19.1
 */
class StatsWriter extends EventsListener {

	protected function captureEvent( string $evt, array $meta = [], array $def = [] ) {
	}

	protected function onShutdown() {
	}

	public function isCommit() :bool {
		return false;
	}

	/**
	 * @return int[]
	 */
	public function getEventStats() :array {
		return [];
	}

	/**
	 * @param int[] $stats
	 */
	public function setEventStats( array $stats = [] ) {
	}
}