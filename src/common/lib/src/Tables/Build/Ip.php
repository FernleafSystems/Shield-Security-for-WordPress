<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;

/**
 * Class Ip
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class Ip extends BaseBuild {

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyQueryFilters() {
		/** @var \ICWP_WPSF_Query_Ips_Select $oSelector */
		$oSelector = $this->getQuerySelector();

		$sList = $this->getParams()[ 'fList' ];
		$oSelector->filterByList( $sList );
		return $this;
	}

	/**
	 * Override to allow other parameter keys for building the table
	 * @return array
	 */
	protected function getCustomParams() {
		return array(
			'fList' => '',
		);
	}

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = array();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var EntryVO $oEntry */
			$aE = $oEntry->getRawData();
			$aE[ 'last_access_at' ] = $this->formatTimestampField( $oEntry->last_access_at );
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aEntries[ $nKey ] = $aE;
		}

		return $aEntries;
	}

	/**
	 * @return Tables\Render\IpBlack|Tables\Render\IpWhite
	 */
	protected function getTableRenderer() {
		$sList = $this->getParams()[ 'fList' ];
		if ( empty( $sList ) || $sList == \ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE ) {
			$sTable = new Tables\Render\IpWhite();
		}
		else {
			$sTable = new Tables\Render\IpBlack();
		}
		return $sTable;
	}
}