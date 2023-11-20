<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Services\Services;

class Options extends BaseShield\Options {

	public function preSave() :void {

		if ( $this->getIpSource() === 'AUTO_DETECT_IP' ) {
			$this->setOpt( 'ipdetect_at', 0 );
		}

		if ( $this->isTrackingEnabled() && !$this->isTrackingPermissionSet() ) {
			$this->setOpt( 'tracking_permission_set_at', Services::Request()->ts() );
		}

		if ( $this->isOptChanged( 'importexport_whitelist' ) ) {
			$this->setOpt( 'importexport_whitelist', \array_unique( \array_filter( \array_map(
				function ( $url ) {
					return Services::Data()->validateSimpleHttpUrl( $url );
				},
				$this->getOpt( 'importexport_whitelist' )
			) ) ) );
		}

		$url = Services::Data()->validateSimpleHttpUrl( $this->getImportExportMasterImportUrl() );
		$this->setOpt( 'importexport_masterurl', $url === false ? '' : $url );
	}

	public function getImportExportMasterImportUrl() :string {
		return (string)$this->getOpt( 'importexport_masterurl', '' );
	}

	public function getBlockdownCfg() :Lib\SiteLockdown\SiteBlockdownCfg {
		return ( new Lib\SiteLockdown\SiteBlockdownCfg() )->applyFromArray(
			\array_merge( [
				'activated_at' => 0,
				'activated_by' => '',
				'disabled_at'  => 0,
				'exclusions'   => [],
				'whitelist_me' => '',
			], $this->getOpt( 'blockdown_cfg' ) )
		);
	}

	/**
	 * @return string[]
	 */
	public function getImportExportWhitelist() :array {
		$list = $this->getOpt( 'importexport_whitelist', [] );
		return \is_array( $list ) ? $list : [];
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
		return ( self::con()->isPremiumActive() || \in_array( $this->getOpt( $key ), [ 'disabled', $default ] ) )
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
		return self::con()->isPremiumActive() || $this->isOpt( 'enable_tracking', 'Y' );
	}

	public function isEnabledWpcli() :bool {
		return self::con()->isPremiumActive()
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