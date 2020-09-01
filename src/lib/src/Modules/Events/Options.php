<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return string
	 */
	public function getDbTable_Events() {
		return $this->getCon()->prefixOption( $this->getDef( 'events_table_name' ) );
	}
}