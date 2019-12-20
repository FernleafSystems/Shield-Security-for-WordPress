<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\IpListSort;

class RetrieveIpsForLists {

	use HandlerConsumer;

	/**
	 * @return string[]
	 */
	public function all() {
		return $this->forLists( [] );
	}

	/**
	 * @return string[]
	 */
	public function white() {
		return $this->forLists( [ 'MW' ] );
	}

	/**
	 * @return string[]
	 */
	public function black() {
		return $this->forLists( [ 'AB', 'MB' ] );
	}

	/**
	 * @return string[]
	 */
	public function blackAuto() {
		return $this->forLists( [ 'AB' ] );
	}

	/**
	 * @return string[]
	 */
	public function blackManual() {
		return $this->forLists( [ 'MB' ] );
	}

	/**
	 * @param string[] $aLists
	 * @return string[]
	 */
	private function forLists( $aLists ) {
		$aResult = [];
		/** @var IPs\Select $oSel */
		$oSel = $this->getDbHandler()
					 ->getQuerySelector()
					 ->addColumnToSelect( 'ip' )
					 ->setIsDistinct( true );
		if ( !empty( $aLists ) ) {
			$oSel->filterByLists( $aLists );
		}
		$aDistinct = $oSel->query();
		if ( is_array( $aDistinct ) ) {
			$aResult = IpListSort::Sort( $aDistinct );
		}
		return $aResult;
	}
}
