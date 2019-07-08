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

	/**
	 * @param array $aAttrs
	 * @return bool
	 */
	protected function getIfDisplayAdminNotice( $aAttrs ) {

		if ( !parent::getIfDisplayAdminNotice( $aAttrs ) ) {
			return false;
		}
		if ( isset( $aAttrs[ 'delay_days' ] ) && is_int( $aAttrs[ 'delay_days' ] )
			 && ( $this->getInstallationDays() < $aAttrs[ 'delay_days' ] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @return bool
	 * @deprecated 8
	 */
	protected function getIfShowAdminNotices() {
		return $this->getMod()->isOpt( 'enable_upgrade_admin_notice', 'Y' );
	}

	/**
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 * @deprecated
	 */
	public function addNotice_rate_plugin() {
		return;
	}

	/**
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 * @deprecated
	 */
	public function addNotice_wizard_welcome() {
		return;
	}

	/**
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 * @deprecated
	 */
	public function addNotice_plugin_update_available() {
		return;
	}

	/**
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 * @deprecated
	 */
	public function addNotice_translate_plugin() {
		return;
	}
}