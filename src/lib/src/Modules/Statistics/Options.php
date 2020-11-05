<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Statistics;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class Options extends BaseShield\Options {

	/**
	 * @return string
	 */
	public function getDbTable_Tallys() {
		return $this->getCon()->prefixOption( $this->getDef( 'statistics_table_name' ) );
	}
}