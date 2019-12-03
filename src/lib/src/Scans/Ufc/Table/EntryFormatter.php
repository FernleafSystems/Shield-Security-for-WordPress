<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Table\BaseFileEntryFormatter;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc\ResultItem;

class EntryFormatter extends BaseFileEntryFormatter {

	/**
	 * @return array
	 */
	public function format() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$oEntry = $this->getEntryVO();
		/** @var ResultItem $oIt */
		$oIt = $this->getResultItem();

		$aE = $this->getBaseData();
		$aE[ 'status' ] = __( 'Unrecognised', 'wp-simple-firewall' );
		return $aE;
	}
}