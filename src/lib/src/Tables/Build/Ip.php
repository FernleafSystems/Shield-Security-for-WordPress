<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Ip
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class Ip extends BaseBuild {

	/**
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
		return [
			'fLists' => '',
		];
	}

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var Options $oOpts */
		$oOpts = $oMod->getOptions();

		$nTransLimit = $oOpts->getOffenseLimit();
		$aEntries = [];
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var IPs\EntryVO $oEntry */
			$aE = $oEntry->getRawDataAsArray();
			$bBlocked = $oEntry->transgressions >= $nTransLimit;
			$aE[ 'last_trans_at' ] = Services::Request()->carbon()->setTimestamp( $oEntry->last_access_at )->diffForHumans();
			$aE[ 'last_access_at' ] = $this->formatTimestampField( $oEntry->last_access_at );
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aE[ 'blocked' ] = $bBlocked ? __( 'Yes' ) : __( 'No' );
			$aE[ 'expires_at' ] = $this->formatTimestampField( $oEntry->last_access_at + $oOpts->getAutoExpireTime() );
			$aEntries[ $nKey ] = $aE;
		}
		return $aEntries;
	}

	/**
	 * @return Tables\Render\IpBlack|Tables\Render\IpWhite
	 */
	protected function getTableRenderer() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$aLists = $this->getParams()[ 'fLists' ];
		if ( empty( $aLists ) || in_array( $oMod::LIST_MANUAL_WHITE, $aLists ) ) {
			$sTable = new Tables\Render\IpWhite();
		}
		else {
			$sTable = new Tables\Render\IpBlack();
		}
		return $sTable;
	}
}