<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Delete extends Base\Delete {

	use CommonFilters;

	/**
	 * @param string $sIp
	 * @param string $sList
	 * @return bool
	 */
	public function deleteIpOnList( $sIp, $sList ) {
		$this->reset();
		if ( Services::IP()->isValidIpOrRange( $sIp ) && !empty( $sList ) ) {
			$this->addWhereEquals( 'ip', $sIp )
				 ->addWhereEquals( 'list', $sList );
		}
		return $this->hasWheres() ? $this->query() : false;
	}

	/**
	 * @param string $sIp
	 * @return bool
	 */
	public function deleteIpFromBlacklists( $sIp ) {
		$this->reset();
		if ( Services::IP()->isValidIpOrRange( $sIp ) ) {
			$this->filterByIp( $sIp )
				 ->filterByBlacklist();
		}
		return $this->hasWheres() ? $this->query() : false;
	}
}