<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseDelete;
use FernleafSystems\Wordpress\Services\Services;

class Delete extends BaseDelete {

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
	 * @return Select
	 */
	protected function getSelector() {
		return ( new Select() )->setTable( $this->getTable() );
	}
}