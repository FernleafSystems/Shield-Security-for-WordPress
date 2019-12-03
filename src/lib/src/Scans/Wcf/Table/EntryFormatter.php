<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseFileEntryFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wcf\ResultItem;

class EntryFormatter extends BaseFileEntryFormatter {

	/**
	 * @return array
	 */
	public function format() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		/** @var ResultItem $oIt */
		$oIt = $this->getResultItem();

		$aE = $this->getBaseData();
		$aE[ 'status' ] = $oIt->is_checksumfail ? __( 'Modified', 'wp-simple-firewall' )
			: ( $oIt->is_missing ? __( 'Missing', 'wp-simple-firewall' ) : __( 'Unknown', 'wp-simple-firewall' ) );
		if ( $oIt->is_missing ) {
			$aE[ 'href_download' ] = false;
		}
		return $aE;
	}
}