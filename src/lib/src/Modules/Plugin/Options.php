<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Options extends Base\ShieldOptions {

	/**
	 * @return string
	 */
	public function getDbTable_GeoIp() {
		return $this->getCon()->prefixOption( $this->getDef( 'geoip_table_name' ) );
	}

	/**
	 * @return string
	 */
	public function getDbTable_Notes() {
		return $this->getCon()->prefixOption( $this->getDef( 'db_notes_name' ) );
	}

	/**
	 * @return string[]
	 */
	public function getDbColumns_GeoIp() {
		return $this->getDef( 'geoip_table_columns' );
	}

	/**
	 * @return string[]
	 */
	public function getDbColumns_Notes() {
		return $this->getDef( 'db_notes_table_columns' );
	}

	/**
	 * @return array
	 */
	public function getGoogleRecaptchaConfig() {
		$aConfig = [
			'key'            => $this->getOpt( 'google_recaptcha_site_key' ),
			'secret'         => $this->getOpt( 'google_recaptcha_secret_key' ),
			'style'          => $this->getOpt( 'google_recaptcha_style' ),
			'style_override' => !$this->getCon()->isPremiumActive()
		];
		if ( $aConfig[ 'style_override' ] ) {
			$aConfig[ 'style' ] = 'light'; // hard-coded light style for non-pro
		}
		return $aConfig;
	}

	/**
	 * @return int
	 */
	public function getImportExportHandshakeExpiresAt() {
		return (int)$this->getOpt( 'importexport_handshake_expires_at', Services::Request()->ts() );
	}

	/**
	 * @return string
	 */
	public function getImportExportMasterImportUrl() {
		return $this->getOpt( 'importexport_masterurl', '' );
	}

	/**
	 * @return string
	 */
	public function getIpSource() {
		return $this->getOpt( 'visitor_address_source' );
	}

	/**
	 * @return bool
	 */
	public function hasImportExportMasterImportUrl() {
		$sMaster = $this->getImportExportMasterImportUrl();
		return !empty( $sMaster );
	}

	/**
	 * @return string
	 */
	public function isIpSourceAutoDetect() {
		return $this->getIpSource() == 'AUTO_DETECT_IP';
	}

	/**
	 * @return bool
	 */
	public function isOnFloatingPluginBadge() {
		return $this->isOpt( 'display_plugin_badge', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isPluginGloballyDisabled() {
		return !$this->isOpt( 'global_enable_plugin_features', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isTrackingEnabled() {
		return $this->isOpt( 'enable_tracking', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isTrackingPermissionSet() {
		return !$this->isOpt( 'tracking_permission_set_at', 0 );
	}

	/**
	 * @return bool
	 */
	public function isImportExportPermitted() {
		return $this->isPremium() && $this->isOpt( 'importexport_enable', 'Y' );
	}

	/**
	 * @return string[]
	 */
	public function getImportExportWhitelist() {
		$aWhitelist = $this->getOpt( 'importexport_whitelist', [] );
		return is_array( $aWhitelist ) ? $aWhitelist : [];
	}

	/**
	 * @param bool $bOnOrOff
	 * @return $this
	 */
	public function setPluginTrackingPermission( $bOnOrOff = true ) {
		return $this->setOpt( 'enable_tracking', $bOnOrOff ? 'Y' : 'N' )
					->setOpt( 'tracking_permission_set_at', Services::Request()->ts() );
	}

	/**
	 * @param string $sSource
	 * @return $this
	 */
	public function setVisitorAddressSource( $sSource ) {
		return $this->setOpt( 'visitor_address_source', $sSource );
	}
}