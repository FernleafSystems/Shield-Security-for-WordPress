<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Options extends Base\ShieldOptions {

	/**
	 * @return string
	 * @deprecated 10.0
	 */
	public function getDbTable_Events() :string {
		return $this->getCon()->prefixOption( $this->getDef( 'events_table_name' ) );
	}
}