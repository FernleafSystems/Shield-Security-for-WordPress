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

	public function getImportExportMasterImportUrl() :string {
		return (string)$this->getOpt( 'importexport_masterurl', '' );
	}

	/**
	 * @return string[]
	 */
	public function getImportExportWhitelist() :array {
		$list = $this->getOpt( 'importexport_whitelist', [] );
		return is_array( $list ) ? $list : [];
	}

	public function getIpSource() :string {
		return (string)$this->getOpt( 'visitor_address_source' );
	}

	public function getReportFrequencyAlert() :string {
		return $this->getFrequency( 'alert' );
	}

	public function getReportFrequencyInfo() :string {
		return $this->getFrequency( 'info' );
	}

	private function getFrequency( string $type ) :string {
		$key = 'frequency_'.$type;
		$default = $this->getOptDefault( $key );
		return ( $this->getCon()->isPremiumActive() || in_array( $this->getOpt( $key ), [ 'disabled', $default ] ) )
			? $this->getOpt( $key )
			: $default;
	}

	public function hasImportExportMasterImportUrl() :bool {
		return !empty( $this->getImportExportMasterImportUrl() );
	}

	public function isPluginGloballyDisabled() :bool {
		return !$this->isOpt( 'global_enable_plugin_features', 'Y' );
	}

	public function isTrackingEnabled() :bool {
		return $this->getCon()->isPremiumActive() || $this->isOpt( 'enable_tracking', 'Y' );
	}

	public function isEnabledWpcli() :bool {
		return $this->getCon()->isPremiumActive()
			   && apply_filters( 'shield/enable_wpcli', $this->isOpt( 'enable_wpcli', 'Y' ) );
	}

	public function isTrackingPermissionSet() :bool {
		return !$this->isOpt( 'tracking_permission_set_at', 0 );
	}

	public function setPluginTrackingPermission( bool $onOrOff = true ) {
		$this->setOpt( 'enable_tracking', $onOrOff ? 'Y' : 'N' )
			 ->setOpt( 'tracking_permission_set_at', Services::Request()->ts() );
	}

	public function setVisitorAddressSource( string $source ) {
		$this->setOpt( 'visitor_address_source', $source );
	}
}