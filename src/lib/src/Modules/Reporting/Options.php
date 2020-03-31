<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return string[]
	 */
	public function getDbColumns_Reports() {
		return $this->getDef( 'reports_table_columns' );
	}

	/**
	 * @return string
	 */
	public function getDbTable_Reports() {
		return $this->getCon()->prefixOption( $this->getDef( 'reports_table_name' ) );
	}
}