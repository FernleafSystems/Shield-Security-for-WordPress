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

	public function getIpSource() :string {
		return (string)$this->getOpt( 'visitor_address_source' );
	}

	public function hasImportExportMasterImportUrl() :bool {
		$sMaster = $this->getImportExportMasterImportUrl();
		return !empty( $sMaster );
	}

	public function isIpSourceAutoDetect() :bool {
		return $this->getIpSource() == 'AUTO_DETECT_IP';
	}

	public function isPluginGloballyDisabled() :bool {
		return !$this->isOpt( 'global_enable_plugin_features', 'Y' );
	}

	public function isTrackingEnabled() :bool {
		return $this->isPremium() || $this->isOpt( 'enable_tracking', 'Y' );
	}

	public function isEnabledWpcli() :bool {
		return $this->isPremium()
			   && apply_filters( 'shield/enable_wpcli', $this->isOpt( 'enable_wpcli', 'Y' ) );
	}

	public function isTrackingPermissionSet() :bool {
		return !$this->isOpt( 'tracking_permission_set_at', 0 );
	}

	/**
	 * @return string[]
	 */
	public function getImportExportWhitelist() :array {
		$whitelist = $this->getOpt( 'importexport_whitelist', [] );
		return is_array( $whitelist ) ? $whitelist : [];
	}

	public function isEnabledShieldNET() :bool {
		return $this->isOpt( 'enable_shieldnet', 'Y' );
	}

	/**
	 * @return $this
	 */
	public function setPluginTrackingPermission( bool $onOrOff = true ) {
		return $this->setOpt( 'enable_tracking', $onOrOff ? 'Y' : 'N' )
					->setOpt( 'tracking_permission_set_at', Services::Request()->ts() );
	}

	/**
	 * @param string $source
	 * @return $this
	 */
	public function setVisitorAddressSource( string $source ) {
		return $this->setOpt( 'visitor_address_source', $source );
	}
}