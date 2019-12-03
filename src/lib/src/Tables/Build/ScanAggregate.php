<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScanActionFromSlug;

/**
 * Class ScanAggregate
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanAggregate extends BaseBuild {

	/**
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = [];

		$aScanRowTracker = [];
		/** @var Scanner\EntryVO $oEntry */
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			if ( empty( $aScanRowTracker[ $oEntry->scan ] ) ) {
				$aScanRowTracker[ $oEntry->scan ] = $oEntry->scan;
				$aEntries[ $oEntry->scan ] = [
					'custom_row' => true,
					'title'      => $oEntry->scan,
				];
			}
			$aEntries[ $nKey ] = ( new ScanActionFromSlug() )
				->getAction( $oEntry->scan )
				->getTableEntryFormatter()
				->setMod( $this->getMod() )
				->setEntryVO( $oEntry )
				->format();
		}

		return $aEntries;
	}

	/**
	 * @return array
	 */
	protected function getParamDefaults() {
		return array_merge(
			parent::getParamDefaults(),
			[
				'orderby' => 'scan',
				'limit'   => PHP_INT_MAX
			]
		);
	}

	/**
	 * Override this to apply table-specific query filters.
	 * @return $this
	 */
	protected function applyCustomQueryFilters() {
		$aParams = $this->getParams();
		/** @var Scanner\Select $oSelector */
		$oSelector = $this->getWorkingSelector();

		if ( $aParams[ 'fIgnored' ] !== 'Y' ) {
			$oSelector->filterByNotIgnored();
		}

		return $this;
	}

	/**
	 * @return Shield\Tables\Render\ScanAggregate
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\ScanAggregate();
	}
}