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
			$this->addWhereEquals( 'ip', $sIp )
				 ->addWhereIn( 'list', [
					 \ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_BLACK,
					 \ICWP_WPSF_FeatureHandler_Ips::LIST_AUTO_BLACK
				 ] );
		}
		return $this->hasWheres() ? $this->query() : false;
	}
}