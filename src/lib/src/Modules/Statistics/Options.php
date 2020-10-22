<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Statistics;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return string
	 */
	public function getDbTable_Tallys() {
		return $this->getCon()->prefixOption( $this->getDef( 'statistics_table_name' ) );
	}
}