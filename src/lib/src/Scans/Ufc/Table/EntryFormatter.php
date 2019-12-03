<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseEntryFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc\ResultItem;

class EntryFormatter extends BaseEntryFormatter {

	/**
	 * @return array
	 */
	public function format() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$oEntry = $this->getEntryVO();
		/** @var ResultItem $oIt */
		$oIt = $this->getResultItem();
		$aE = $this->getEntryVO()->getRawDataAsArray();
		$aE[ 'path' ] = $oIt->path_fragment;
		$aE[ 'status' ] = __( 'Unrecognised', 'wp-simple-firewall' );
		$aE[ 'created_at' ] = $this->formatTimestampField( $oEntry->created_at );
		$aE[ 'href_download' ] = $oMod->createFileDownloadLink( $oEntry );
		return $aE;
	}
}