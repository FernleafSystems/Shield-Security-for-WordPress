<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class ScanAggregate extends ScanBase {

	/**
	 * @return $this
	 */
	protected function preBuildTable() {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();

		foreach ( $this->getIncludedScanSlugs() as $scan ) {
			$mod->getScanCon( $scan )->cleanStalesResults();
		}

		return $this;
	}

	/**
	 * @return array[]
	 */
	public function getEntriesFormatted() :array {
		// first filter out PTG results as we process them a bit separately.
		$ptgScanEntries = [];
		/** @var Scanner\EntryVO[] $raw */
		$raw = $this->getEntriesRaw();
		foreach ( $raw as $key => $entry ) {
			if ( $entry->scan == 'ptg' ) {
				unset( $raw[ $key ] );
				$ptgScanEntries[ $key ] = $entry;
			}
		}

		$aEntries = $this->processEntriesGroup( $raw );

		// Group all PTG entries together
		usort( $ptgScanEntries, function ( $oE1, $oE2 ) {
			/** @var $oE1 EntryVO */
			/** @var $oE2 EntryVO */
			return strcasecmp( $oE1->meta[ 'path_full' ], $oE2->meta[ 'path_full' ] );
		} );

		return array_merge(
			$aEntries,
			$this->processEntriesGroup( $ptgScanEntries )
		);
	}

	/**
	 * @param Scanner\EntryVO[] $entries
	 * @return array[]
	 */
	private function processEntriesGroup( array $entries ) {
		$processed = [];

		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var HackGuard\Strings $strings */
		$strings = $mod->getStrings();
		$scanNames = $strings->getScanNames();

		$aScanRowTracker = [];
		foreach ( $entries as $key => $entry ) {
			if ( empty( $aScanRowTracker[ $entry->scan ] ) ) {
				$aScanRowTracker[ $entry->scan ] = $entry->scan;
				$processed[ $entry->scan ] = [
					'custom_row' => true,
					'title'      => $scanNames[ $entry->scan ],
				];
			}
			$processed[ $key ] = $mod
				->getScanCon( $entry->scan )
				->getTableEntryFormatter()
				->setMod( $this->getMod() )
				->setEntryVO( $entry )
				->format();
		}

		return $processed;
	}

	protected function getParamDefaults() :array {
		return array_merge(
			parent::getParamDefaults(),
			[ 'orderby' => 'scan', ]
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

		$oSelector->filterByScans( $this->getIncludedScanSlugs() );

		return $this;
	}

	/**
	 * @return string[]
	 */
	private function getIncludedScanSlugs() :array {
		return [ 'mal' ];
	}

	protected function getCustomParams() :array {
		return [];
	}

	/**
	 * @return Shield\Tables\Render\WpListTable\ScanAggregate
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\WpListTable\ScanAggregate();
	}
}