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
	 * @return array[]
	 */
	protected function getEntriesFormatted() {
		$aEntries = [];

		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$oConverter = ( new Scan\Results\ConvertBetweenTypes() )
			->setScanActionVO( $this->getScanActionVO() );
		foreach ( $this->getEntriesRaw() as $nKey => $oEntry ) {
			/** @var Shield\Databases\Scanner\EntryVO $oEntry */
			/** @var Shield\Scans\Ptg\ResultItem $oIt */
			$oIt = $oConverter->convertVoToResultItem( $oEntry );
			$aE = $oEntry->getRawDataAsArray();
			$aE[ 'path' ] = $oIt->path_fragment;
			$aE[ 'status' ] = $oIt->is_different ? __( 'Modified', 'wp-simple-firewall' )
				: ( $oIt->is_missing ? __( 'Missing', 'wp-simple-firewall' ) : __( 'Unrecognised', 'wp-simple-firewall' ) );
			$aE[ 'ignored' ] = $this->formatIsIgnored( $oEntry );
			$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
			$aE[ 'href_download' ] = $oIt->is_missing ? false : $oMod->createFileDownloadLink( $oEntry );
			$aEntries[ $nKey ] = $aE;
		}

		return $aEntries;
	}

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