<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScanBase
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanBase extends Base {

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyQueryFilters() {
		$aParams = $this->getParams();
		/** @var \ICWP_WPSF_Query_Scanner_Select $oSelector */
		$oSelector = $this->getQuerySelector();

		$oSelector->filterByScan( $aParams[ 'fScan' ] );
		if ( $aParams[ 'fIgnored' ] !== 'Y' ) {
			$oSelector->filterByNotIgnored();
		}

		return $this;
	}

	/**
	 * Override to allow other parameter keys for building the table
	 * @return array
	 */
	protected function getCustomParams() {
		return array(
			'fScan'    => 'wcf',
			'fSlug'    => '',
			'fIgnored' => 'N',
		);
	}

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = array();

		$sYou = Services::IP()->getRequestIp();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var \ICWP_WPSF_AuditTrailEntryVO $oEntry */
			$aE = $oEntry->getRawData();
			$aE[ 'event' ] = str_replace( '_', ' ', sanitize_text_field( $oEntry->event ) );
			$aE[ 'message' ] = stripslashes( sanitize_text_field( $oEntry->message ) );
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			if ( $oEntry->getIp() == $sYou ) {
				$aE[ 'your_ip' ] = '<br /><small>('._wpsf__( 'Your IP' ).')</small>';
			}
			else {
				$aE[ 'your_ip' ] = '';
			}
			$aEntries[ $nKey ] = $aE;
		}
		return $aEntries;
	}

	/**
	 * @return Tables\Render\AuditTrail
	 */
	protected function getTableRenderer() {
		return new Tables\Render\AuditTrail();
	}
}