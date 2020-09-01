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
	 * @return array
	 */
	public function getCustomExclusions() {
		$aEx = $this->getOpt( 'custom_exclusions' );
		return is_array( $aEx ) ? $aEx : [];
	}

	/**
	 * @return string[]
	 * @deprecated 9.2.0
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

	/**
	 * @return int
	 */
	public function getLimitRequestCount() {
		return (int)$this->getOpt( 'limit_requests' );
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
	public function getMaxEntries() {
		return (int)$this->getOpt( 'max_entries' );
	}

	/**
	 * @return string[]
	 */
	public function getReqTypeExclusions() {
		$aEx = $this->getOpt( 'type_exclusions' );
		return is_array( $aEx ) ? $aEx : [];
	}

	/**
	 * @return bool
	 */
	public function isTrafficLoggerEnabled() {
		return $this->isOpt( 'enable_traffic', 'Y' ) && $this->isOpt( 'enable_logger', 'Y' )
			   && $this->getMaxEntries() > 0 && $this->getAutoCleanDays() > 0;
	}

	/**
	 * @return bool
	 */
	public function isTrafficLimitEnabled() {
		return $this->isTrafficLoggerEnabled() && $this->isOpt( 'enable_limiter', 'Y' )
			   && ( $this->getLimitTimeSpan() > 0 ) && ( $this->getLimitRequestCount() > 0 );
	}
}