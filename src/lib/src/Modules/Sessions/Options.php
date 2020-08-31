<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return string[]
	 * @deprecated 9.2.0
	 */
	public function getDbColumns_Sessions() {
		return $this->getDef( 'sessions_table_columns' );
	}

	/**
	 * @return string
	 */
	public function getDbTable_Sessions() {
		return $this->getCon()->prefixOption( $this->getDef( 'sessions_table_name' ) );
	}
}