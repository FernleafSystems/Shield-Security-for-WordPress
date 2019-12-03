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

		$oActionGetter = new ScanActionFromSlug();
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Scanner\EntryVO $oEntry */
			$aEntries[ $nKey ] = $oActionGetter->getAction( $oEntry->scan )
											   ->getTableEntryFormatter()
											   ->setMod( $this->getMod() )
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