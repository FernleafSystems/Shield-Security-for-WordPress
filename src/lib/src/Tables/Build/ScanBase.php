<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScanBase
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanBase extends BaseBuild {

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyCustomQueryFilters() {
		$aParams = $this->getParams();
		/** @var Scanner\Select $oSelector */
		$oSelector = $this->getWorkingSelector();

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
		return [
			'fScan'    => 'wcf',
			'fSlug'    => '',
			'fIgnored' => 'N',
		];
	}

	/**
	 * @return array
	 */
	protected function getParamDefaults() {
		$aP = parent::getParamDefaults();
		$aP[ 'limit' ] = PHP_INT_MAX;
		return $aP;
	}

	/**
	 * @param Scanner\EntryVO $oEntry
	 * @return string
	 */
	protected function formatIsIgnored( $oEntry ) {
		return ( $oEntry->ignored_at > 0 && Services::Request()->ts() > $oEntry->ignored_at ) ? __( 'Yes' ) : __( 'No' );
	}
}