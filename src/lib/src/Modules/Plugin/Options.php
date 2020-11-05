<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Options extends BaseShield\Options {

	public function getCaptchaConfig() :array {
		return [
			'provider' => $this->getOpt( 'captcha_provider', 'grecaptcha' ),
			'key'      => $this->getOpt( 'google_recaptcha_site_key' ),
			'secret'   => $this->getOpt( 'google_recaptcha_secret_key' ),
			'theme'    => $this->getOpt( 'google_recaptcha_style' ),
		];
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

	public function getShieldNetApiData() :array {
		$d = $this->getOpt( 'snapi_data', [] );
		return is_array( $d ) ? $d : [];
	}

	/**
	 * @return bool
	 */
	public function hasImportExportMasterImportUrl() :bool {
		$sMaster = $this->getImportExportMasterImportUrl();
		return !empty( $sMaster );
	}

	public function isIpSourceAutoDetect() :bool {
		return $this->getIpSource() == 'AUTO_DETECT_IP';
	}

	/**
	 * @return bool
	 */
	public function isPluginGloballyDisabled() {
		return !$this->isOpt( 'global_enable_plugin_features', 'Y' );
	}

	public function isEnabledMainWP() :bool {
		return $this->isOpt( 'enable_mainwp', 'Y' );
	}

	public function isTrackingEnabled() :bool {
		return $this->isOpt( 'enable_tracking', 'Y' );
	}

	public function isEnabledWpcli() :bool {
		return $this->isPremium() && $this->isOpt( 'enable_wpcli', 'Y' );
	}

	public function isTrackingPermissionSet() :bool {
		return !$this->isOpt( 'tracking_permission_set_at', 0 );
	}

	public function isImportExportPermitted() :bool {
		return $this->isPremium() && $this->isOpt( 'importexport_enable', 'Y' );
	}

	/**
	 * @return string[]
	 */
	public function getImportExportWhitelist() :array {
		$whitelist = $this->getOpt( 'importexport_whitelist', [] );
		return is_array( $whitelist ) ? $whitelist : [];
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