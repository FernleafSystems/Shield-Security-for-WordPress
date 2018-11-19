<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseDelete;

class Delete extends BaseDelete {

	use BaseTraffic;

	/**
	 * @return Select
	 */
	protected function getSelector() {
		return ( new Select() )->setTable( $this->getTable() );
	}
}