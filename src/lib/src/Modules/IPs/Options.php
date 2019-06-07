<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\Options {

	/**
	 * @return string[]
	 */
	public function getDbColumns_IPs() {
		return $this->getDef( 'ip_list_table_columns' );
	}
	/**
	 * @return string
	 */
	public function getDbTable_IPs() {
		return $this->getCon()->prefixOption( $this->getDef( 'ip_lists_table_name' ) );
	}
}