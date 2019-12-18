<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;

class RetrieveIpsForLists {

	use HandlerConsumer;

	/**
	 * @return string[]
	 */
	public function all() {
		/** @var IPs\Select $oSel */
		$oSel = $this->getDbHandler()->getQuerySelector();
		return $oSel->getDistinctIps();
	}

	/**
	 * @return string[]
	 */
	public function white() {
		$aResult = [];
		/** @var IPs\Select $oSel */
		$oSel = $this->getDbHandler()->getQuerySelector();
		$aDistinct = $oSel->addColumnToSelect( 'ip' )
						  ->filterByLists( [ 'MW' ] )
						  ->setIsDistinct( true )
						  ->query();
		if ( is_array( $aDistinct ) ) {
			$aResult = array_filter( $aDistinct );
			natcasesort( $aResult );
		}
		return $aResult;
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
		$oSel = $this->getDbHandler()->getQuerySelector();
		$aDistinct = $oSel->addColumnToSelect( 'ip' )
						  ->filterByLists( $aLists )
						  ->setIsDistinct( true )
						  ->query();
		if ( is_array( $aDistinct ) ) {
			$aResult = array_filter( $aDistinct );
			natcasesort( $aResult );
		}
		return $aResult;
	}
}
