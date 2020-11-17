<?php

use FernleafSystems\Wordpress\Plugin\Shield;

/**
 * @deprecated 10.1
 */
class ICWP_WPSF_Processor_HackProtect_Wpv extends ICWP_WPSF_Processor_ScanBase {

	const SCAN_SLUG = 'wpv';

	/**
	 * @param bool             $bDoAutoUpdate
	 * @param \stdClass|string $mItem
	 * @return bool
	 */
	public function autoupdateVulnerablePlugins( $bDoAutoUpdate, $mItem ) {
		return $bDoAutoUpdate;
	}

	/**
	 * @param array $aColumns
	 * @return array
	 */
	public function fCountColumns( $aColumns ) {
		return $aColumns;
	}

	public function addPluginVulnerabilityRows() {
	}

	public function addVulnerablePluginStatusView() {
	}

	/**
	 * FILTER
	 * @param array $aViews
	 * @return array
	 */
	public function addPluginsStatusViewLink( $aViews ) {
		return $aViews;
	}

	/**
	 * FILTER
	 * @param array $aPlugins
	 * @return array
	 */
	public function filterPluginsToView( $aPlugins ) {
		return $aPlugins;
	}

	/**
	 * @param string $sPluginFile
	 * @param array  $aPluginData
	 */
	public function attachVulnerabilityWarning( $sPluginFile, $aPluginData ) {
	}

	/**
	 * @param string $sFile
	 * @return Shield\Scans\Wpv\WpVulnDb\WpVulnVO[]
	 */
	private function getPluginVulnerabilities( $sFile ) {
		return [];
	}

	/**
	 * @return bool
	 */
	private function countVulnerablePlugins() {
		return 0;
	}
}