<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan;

/**
 * Class ScanPtg
 * @package FernleafSystems\Wordpress\Plugin\Shield\Tables\Build
 */
class ScanPtg extends ScanBase {

	/**
	 * Since we can't select items by slug directly from the scan results DB
	 * we have to post-filter the results.
	 * @param Shield\Databases\Scanner\EntryVO[] $aEntries
	 * @return Shield\Databases\Scanner\EntryVO[]
	 */
	protected function postSelectEntriesFilter( $aEntries ) {
		$aParams = $this->getParams();

		if ( !empty( $aParams[ 'fSlug' ] ) ) {

			/** @var Shield\Scans\Ptg\ResultsSet $oSlugResults */
			$oSlugResults = ( new Scan\Results\ConvertBetweenTypes() )
				->setScanActionVO( ( new Scan\ScanActionFromSlug() )->getAction( 'ptg' ) )
				->fromVOsToResultsSet( $aEntries );
			$oSlugResults = $oSlugResults->getResultsSetForSlug( $aParams[ 'fSlug' ] );

			foreach ( $aEntries as $key => $oVo ) {
				if ( !$oSlugResults->getItemExists( $oVo->hash ) ) {
					unset( $aEntries[ $key ] );
				}
			}
		}

		return array_values( $aEntries );
	}

	/**
	 * @return Shield\Tables\Render\ScanPtg
	 */
	protected function getTableRenderer() {
		return new Shield\Tables\Render\ScanPtg();
	}
}