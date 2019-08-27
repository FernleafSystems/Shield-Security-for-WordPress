<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_BasePlugin extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function init() {
		parent::init();
		$oFO = $this->getMod();

		$sFunc = $oFO->isOpt( 'delete_on_deactivate', 'Y' ) ? '__return_true' : '__return_false';
		add_filter( $oFO->prefix( 'delete_on_deactivate' ), $sFunc );
	}

	/**
	 */
	public function run() {
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		$aData = parent::tracking_DataCollect( $aData );
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$sSlug = $oFO->getSlug();
		if ( empty( $aData[ $sSlug ][ 'options' ][ 'unique_installation_id' ] ) ) {
			$aData[ $sSlug ][ 'options' ][ 'unique_installation_id' ] = $oFO->getPluginInstallationId();
		}
		return $aData;
	}
}