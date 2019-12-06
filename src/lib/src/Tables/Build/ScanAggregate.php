<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;
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
		// first filter out PTG results as we process them a bit separately.
		$aPtgScanEntries = [];
		$aRaw = $this->getEntriesRaw();
		/** @var $oEntry Scanner\EntryVO */
		foreach ( $aRaw as $nKeyId => $oEntry ) {
			if ( $oEntry->scan == 'ptg' ) {
				unset( $aRaw[ $nKeyId ] );
				$aPtgScanEntries[ $nKeyId ] = $oEntry;
			}
		}

		$aEntries = $this->processEntriesGroup( $aRaw );

		// Group all PTG entries together
		usort( $aPtgScanEntries, function ( $oE1, $oE2 ) {
			/** @var $oE1 EntryVO */
			/** @var $oE2 EntryVO */
			return strcasecmp( $oE1->meta[ 'path_full' ], $oE2->meta[ 'path_full' ] );
		} );

		return array_merge(
			$aEntries,
			$this->processEntriesGroup( $aPtgScanEntries )
		);
	}

	/**
	 * @param Scanner\EntryVO[] $aEntries
	 * @return array[]
	 */
	private function processEntriesGroup( $aEntries ) {
		$aProcessedEntries = [];

		/** @var Shield\Modules\HackGuard\Strings $oStrings */
		$oStrings = $this->getMod()->getStrings();
		$aScanNames = $oStrings->getScanNames();

		$aScanRowTracker = [];
		foreach ( $aEntries as $nKey => $oEntry ) {
			if ( empty( $aScanRowTracker[ $oEntry->scan ] ) ) {
				$aScanRowTracker[ $oEntry->scan ] = $oEntry->scan;
				$aProcessedEntries[ $oEntry->scan ] = [
					'custom_row' => true,
					'title'      => $aScanNames[ $oEntry->scan ],
				];
			}
			$aProcessedEntries[ $nKey ] = ( new ScanActionFromSlug() )
				->getAction( $oEntry->scan )
				->getTableEntryFormatter()
				->setMod( $this->getMod() )
				->setEntryVO( $oEntry )
				->format();
		}

		return $aProcessedEntries;
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

		if ( empty( $aParams[ 'fIgnored' ] ) || $aParams[ 'fIgnored' ] !== 'Y' ) {
			$oSelector->filterByNotIgnored();
		}

		$oSelector->filterByScans( [ 'mal', 'wcf', 'ufc', 'ptg' ] );

		return $this;
	}

	/**
	 * @return Shield\Tables\Render\ScanAggregate
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\ScanAggregate();
	}
}