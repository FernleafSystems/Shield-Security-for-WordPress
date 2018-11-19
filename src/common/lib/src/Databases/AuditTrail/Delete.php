<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseDelete;

class Delete extends BaseDelete {
	/**
	 * @return Select
	 */
	protected function getSelector() {
		return ( new Select() )->setTable( $this->getTable() );
	}
}