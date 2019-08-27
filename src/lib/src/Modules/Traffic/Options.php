<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return int
	 */
	public function getAutoCleanDays() {
		return (int)$this->getOpt( 'auto_clean' );
	}

	/**
	 * @return int
	 */
	public function getLimitTimeSpan() {
		return (int)$this->getOpt( 'limit_time_span' );
	}

	/**
	 * @return int
	 */
	public function getLimitRequestCount() {
		return (int)$this->getOpt( 'limit_requests' );
	}

	/**
	 * @return bool
	 */
	public function isTrafficLimitEnabled() {
		return ( $this->getLimitTimeSpan() > 0 ) && ( $this->getLimitRequestCount() > 0 );
	}

	/**
	 * @return string[]
	 */
	public function getDbColumns_TrafficLog() {
		return $this->getDef( 'traffic_table_columns' );
	}

	/**
	 * @return string
	 */
	public function getDbTable_TrafficLog() {
		return $this->getCon()->prefixOption( $this->getDef( 'traffic_table_name' ) );
	}
}