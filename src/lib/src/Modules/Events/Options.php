<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return string[]
	 * @deprecated 9.2.0
	 */
	public function getDbColumns_Events() {
		return $this->getDef( 'events_table_columns' );
	}

	/**
	 * @return string
	 */
	public function getDbTable_Events() {
		return $this->getCon()->prefixOption( $this->getDef( 'events_table_name' ) );
	}
}