<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use CommonFilters;

	/**
	 * @param string $sIp
	 * @return bool
	 */
	public function getIpOnBlackLists( $sIp ) {
		return $this->reset()
					->filterByIp( $sIp )
					->filterByLists( [
						\ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK,
						\ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_BLACK
					] )
					->first();
	}

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