<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Tally;

class Delete extends \FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseDelete {

	/**
	 * @return Select
	 */
	protected function getSelector() {
		return ( new Select() )->setTable( $this->getTable() );
	}
}