<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use CommonFilters;

	public $print = false;

	/**
	 * @return string[]
	 */
	public function getDistinctIps() {
		return $this->getDistinct_FilterAndSort( 'ip' );
	}

	/**
	 * @param string $sIp
	 * @return bool
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
	 */
	public function allFromList( $sList ) {
		/** @var EntryVO[] $aRes */
		return $this->reset()
					->filterByList( $sList )
					->query();
	}
}