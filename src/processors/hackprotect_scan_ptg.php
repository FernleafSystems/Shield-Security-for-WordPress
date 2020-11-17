<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Services;

/**
 * @deprecated 10.1
 */
class ICWP_WPSF_Processor_HackProtect_Ptg extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'ptg';

	/**
	 * @param array  $aLinks
	 * @param string $sPluginFile
	 * @return string[]
	 */
	public function addActionLinkRefresh( $aLinks, $sPluginFile ) {
		return $aLinks;
	}

	public function printPluginReinstallDialogs() {
	}
}