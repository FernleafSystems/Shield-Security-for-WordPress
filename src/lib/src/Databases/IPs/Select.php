<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use CommonFilters;

	/**
	 * @param string $sIp
	 * @return bool
	 * @deprecated 8.5
	 */
	public function getIpOnBlackLists( $sIp ) {
		return $this->reset()
					->filterByIp( $sIp )
					->filterByBlacklist()
					->first();
	}

	/**
	 * @return EntryVO[]
	 */
	public function getAllBlocked() {
		/** @var EntryVO[] $aRes */
		return $this->reset()
					->filterByBlocked( true )
					->filterByBlacklist()
					->query();
	}

	/**
	 * @param string $sList
	 * @return EntryVO[]
	 * @deprecated 8.5
	 */
	public function allFromList( $sList ) {
		/** @var EntryVO[] $aRes */
		return $this->reset()
					->filterByList( $sList )
					->query();
	}

	/**
	 * @return string[]
	 * @deprecated 8.5
	 */
	public function getDistinctIps() {
		return $this->getDistinct_FilterAndSort( 'ip' );
	}
}