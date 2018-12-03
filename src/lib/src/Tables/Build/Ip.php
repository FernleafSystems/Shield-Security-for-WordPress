<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
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
	protected function applyCustomQueryFilters() {
		/** @var IPs\Select $oSelector */
		$oSelector = $this->getWorkingSelector();
		$oSelector->filterByLists( $this->getParams()[ 'fLists' ] );
		return $this;
	}

	/**
	 * Override to allow other parameter keys for building the table
	 * @return array
	 */
	protected function getCustomParams() {
		return array(
			'fLists' => '',
		);
	}

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$nTransLimit = $oMod->getOptTransgressionLimit();

		$aEntries = array();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var IPs\EntryVO $oEntry */
			$aE = $oEntry->getRawDataAsArray();
			$bBlocked = $oEntry->transgressions >= $nTransLimit;
			$aE[ 'last_trans_at' ] = ( new \Carbon\Carbon() )->setTimestamp( $oEntry->last_access_at )->diffForHumans();
			$aE[ 'last_access_at' ] = $this->formatTimestampField( $oEntry->last_access_at );
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aE[ 'blocked' ] = $bBlocked ? __( 'Yes' ) : __( 'No' );
			$aE[ 'expires_at' ] = $this->formatTimestampField( $oEntry->last_access_at + $oMod->getAutoExpireTime() );
			$aEntries[ $nKey ] = $aE;
		}
		return $aEntries;
	}

	/**
	 * @return Tables\Render\IpBlack|Tables\Render\IpWhite
	 */
	protected function getTableRenderer() {
		$aLists = $this->getParams()[ 'fLists' ];
		if ( empty( $aLists ) || in_array( \ICWP_WPSF_FeatureHandler_Ips::LIST_MANUAL_WHITE, $aLists ) ) {
			$sTable = new Tables\Render\IpWhite();
		}
		else {
			$sTable = new Tables\Render\IpBlack();
		}
		return $sTable;
	}
}