<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Traffic extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return false|Shield\Databases\Traffic\Handler
	 */
	public function getDbHandler_Traffic() {
		return $this->getDbH( 'traffic' );
	}

	/**
	 * Hooked to the plugin's main plugin_shutdown action
	 */
	public function onPluginShutdown() {
		if ( $this->isAutoDisable() && Services::Request()->ts() - $this->getAutoDisableAt() > 0 ) {
			$this->setOpt( 'auto_disable', 'N' )
				 ->setOpt( 'autodisable_at', 0 )
				 ->setIsMainFeatureEnabled( false );
		}
		parent::onPluginShutdown();
	}

	/**
	 * We clean the database after saving.
	 */
	protected function doExtraSubmitProcessing() {
		$this->setOpt( 'autodisable_at', $this->isAutoDisable() ? Services::Request()->ts() + WEEK_IN_SECONDS : 0 );

		$aExcls = $this->getCustomExclusions();
		foreach ( $aExcls as &$sExcl ) {
			$sExcl = trim( esc_js( $sExcl ) );
		}
		$this->setOpt( 'custom_exclusions', array_filter( $aExcls ) );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() {
		$oIp = Services::IP();
		return $oIp->isValidIp_PublicRange( $oIp->getRequestIp() )
			   && ( $this->getDbHandler_Traffic() instanceof Shield\Databases\Traffic\Handler )
			   && $this->getDbHandler_Traffic()->isReady()
			   && parent::isReadyToExecute();
	}

	/**
	 * @param string $sSection
	 * @return array
	 */
	protected function getSectionWarnings( $sSection ) {
		$aWarnings = [];

		$oIp = Services::IP();
		if ( !$oIp->isValidIp_PublicRange( $oIp->getRequestIp() ) ) {
			$aWarnings[] = __( 'Traffic Watcher will not run because visitor IP address detection is not correctly configured.', 'wp-simple-firewall' );
		}

		switch ( $sSection ) {
			case 'section_traffic_limiter':
				if ( !$this->isPremium() ) {
					$aWarnings[] = sprintf( __( '%s is a Pro-only feature.', 'wp-simple-firewall' ), __( 'Traffic Limiting', 'wp-simple-firewall' ) );
				}
				break;
		}

		return $aWarnings;
	}

	/**
	 * @return array
	 */
	protected function getExclusions() {
		$aEx = $this->getOpt( 'type_exclusions' );
		return is_array( $aEx ) ? $aEx : [];
	}

	/**
	 * @return array
	 */
	public function getCustomExclusions() {
		$aEx = $this->getOpt( 'custom_exclusions' );
		return is_array( $aEx ) ? $aEx : [];
	}

	/**
	 * @return int
	 */
	public function getAutoDisableAt() {
		return (int)$this->getOpt( 'autodisable_at' );
	}

	/**
	 * @return string
	 */
	public function getAutoDisableTimestamp() {
		return Services::WpGeneral()->getTimeStampForDisplay( $this->getAutoDisableAt() );
	}

	/**
	 * @return bool
	 */
	public function isAutoDisable() {
		return $this->isOpt( 'auto_disable', 'Y' );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Ajax() {
		return !in_array( 'ajax', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Cron() {
		return !in_array( 'cron', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_LoggedInUser() {
		return !in_array( 'logged_in', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Search() {
		return !in_array( 'search', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Simple() {
		return !in_array( 'simple', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isIncluded_Uptime() {
		return !in_array( 'uptime', $this->getExclusions() );
	}

	/**
	 * @return bool
	 */
	public function isLogUsers() {
		return $this->isIncluded_LoggedInUser();
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Traffic';
	}

	/**
	 * @return Shield\Databases\Traffic\Handler
	 * @deprecated 8.1.2
	 */
	protected function loadDbHandler() {
		return new Shield\Databases\Traffic\Handler();
	}

	/**
	 * @return int
	 * @deprecated 8.1
	 */
	public function getMaxEntries() {
		return (int)$this->getOpt( 'max_entries' );
	}
}