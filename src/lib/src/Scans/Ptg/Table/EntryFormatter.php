<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseEntryFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg\ResultItem;

class EntryFormatter extends BaseEntryFormatter {

	/**
	 * @return array
	 */
	public function format() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oEntry = $this->getEntryVO();
		/** @var ResultItem $oIt */
		$aE = $oEntry->getRawDataAsArray();
		$aE[ 'path' ] = $oIt->path_fragment;
		$aE[ 'status' ] = $oIt->is_different ? __( 'Modified', 'wp-simple-firewall' )
			: ( $oIt->is_missing ? __( 'Missing', 'wp-simple-firewall' ) : __( 'Unrecognised', 'wp-simple-firewall' ) );
		$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
		$aE[ 'href_download' ] = $oIt->is_missing ? false : $oMod->createFileDownloadLink( $oEntry );
		return $aE;
	}
}