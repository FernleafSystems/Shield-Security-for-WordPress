<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;

/**
 * @deprecated 10.1
 */
class ICWP_WPSF_Processor_Plugin extends Modules\BaseShield\ShieldProcessor {

	public function run() {
	}

	public function printDashboardWidget() {
	}

	/**
	 * @return \ICWP_WPSF_Processor_Plugin_Tracking
	 */
	protected function getSubProTracking() {
		return $this->getSubPro( 'tracking' );
	}

	/**
	 * @return array
	 */
	protected function getSubProMap() :array {
		return [
			'tracking' => 'ICWP_WPSF_Processor_Plugin_Tracking',
		];
	}

	public function printAdminFooterItems() {
	}

	/**
	 * Sets this plugin to be the first loaded of all the plugins.
	 */
	private function printToastTemplate() {
	}

	private function printPluginDeactivateSurvey() {
	}

	/**
	 * @deprecated 10.1
	 */
	public function dumpTrackingData() {
	}

	public function runDailyCron() {
	}

	/**
	 * Lets you remove certain plugin conflicts that might interfere with this plugin
	 */
	protected function removePluginConflicts() {
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param array $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		return parent::tracking_DataCollect( $aData );
	}
}