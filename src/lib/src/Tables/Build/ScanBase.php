<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class ScanBase
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanBase extends BaseBuild {

	protected function buildEmpty() :string {
		return sprintf( '<div class="alert alert-success m-0">%s</div>',
			__( "The previous scan either didn't detect any items that require your attention or they've already been repaired.", 'wp-simple-firewall' ) );
	}

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		$aEntries = [];

		/** @var ModCon $mod */
		$mod = $this->getMod();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Scanner\EntryVO $oEntry */
			$aEntries[ $nKey ] = $mod->getScanCon( $oEntry->scan )
									  ->getTableEntryFormatter()
									  ->setEntryVO( $oEntry )
									  ->format();
		}

		return $aEntries;
	}

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

	protected function getCustomParams() :array {
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
		return array_merge(
			parent::getParamDefaults(),
			[ 'limit' => PHP_INT_MAX ]
		);
	}

	/**
	 * @param Scanner\EntryVO $oEntry
	 * @return string
	 */
	protected function formatIsIgnored( $oEntry ) {
		return ( $oEntry->ignored_at > 0 && Services::Request()->ts() > $oEntry->ignored_at ) ?
			__( 'Yes' ) : __( 'No' );
	}
}