<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Statistics;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return string[]
	 * @deprecated 9.2.0
	 */
	public function getDbColumns_Tallys() {
		return $this->getDef( 'statistics_table_columns' );
	}

	/**
	 * @return string
	 */
	public function getDbTable_Tallys() {
		return $this->getCon()->prefixOption( $this->getDef( 'statistics_table_name' ) );
	}
}