<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Statistics extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return string
	 */
	public function getEventsTableName() {
		return $this->getCon()->prefixOption( $this->getDef( 'events_table_name' ) );
	}

	/**
	 * @return string
	 */
	public function getFullEventsTableName() {
		return Services::WpDb()->getPrefix().$this->getEventsTableName();
	}

	/**
	 * @return Shield\Modules\Statistics\Options
	 */
	protected function loadOptions() {
		return new Shield\Modules\Statistics\Options();
	}

	/**
	 * @return Shield\Modules\Statistics\Strings
	 */
	protected function loadStrings() {
		return new Shield\Modules\Statistics\Strings();
	}
}