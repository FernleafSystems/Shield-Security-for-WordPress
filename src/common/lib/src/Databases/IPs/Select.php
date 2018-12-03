<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use CommonFilters;

	/**
	 * @param string $sList
	 * @return EntryVO[]
	 */
	public function allFromList( $sList ) {
		/** @var EntryVO[] $aRes */
		$aRes = $this->reset()
					 ->filterByList( $sList )
					 ->query();
		return $aRes;
	}
}