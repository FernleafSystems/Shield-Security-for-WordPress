<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

class Options extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options {

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

	public function isPluginGloballyDisabled() :bool {
		return !$this->isOpt( 'global_enable_plugin_features', 'Y' );
	}

	public function isTrackingEnabled() :bool {
		return self::con()->isPremiumActive() || $this->isOpt( 'enable_tracking', 'Y' );
	}

	public function isTrackingPermissionSet() :bool {
		return !$this->isOpt( 'tracking_permission_set_at', 0 );
	}

	/**
	 * @deprecated 19.1
	 */
	public function setVisitorAddressSource( string $source ) {
		self::con()->opts->optSet( 'visitor_address_source', $source );
	}

	/**
	 * @deprecated 19.1
	 */
	public function isEnabledWpcli() :bool {
		return self::con()->isPremiumActive()
			   && apply_filters( 'shield/enable_wpcli', $this->isOpt( 'enable_wpcli', 'Y' ) );
	}

	/**
	 * @return string[]
	 * @deprecated 19.1
	 */
	public function getImportExportWhitelist() :array {
		$list = $this->getOpt( 'importexport_whitelist', [] );
		return \is_array( $list ) ? $list : [];
	}

	/**
	 * @deprecated 19.1
	 */
	public function getImportExportMasterImportUrl() :string {
		return (string)$this->getOpt( 'importexport_masterurl', '' );
	}
}