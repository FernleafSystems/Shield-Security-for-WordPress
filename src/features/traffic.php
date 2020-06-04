<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;
use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_FeatureHandler_Traffic extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @return false|Shield\Databases\Traffic\Handler
	 */
	public function getDbHandler_Traffic() {
		return $this->getDbH( 'traffic' );
	}

	/**
	 * We clean the database after saving.
	 */
	protected function preProcessOptions() {
		/** @var Traffic\Options $oOpts */
		$oOpts = $this->getOptions();

		$aExcls = $oOpts->getCustomExclusions();
		foreach ( $aExcls as &$sExcl ) {
			$sExcl = trim( esc_js( $sExcl ) );
		}
		$oOpts->setOpt( 'custom_exclusions', array_filter( $aExcls ) );
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
		/** @var Traffic\Options $oOpts */
		$oOpts = $this->getOptions();

		$aWarnings = [];

		$oIp = Services::IP();
		if ( !$oIp->isValidIp_PublicRange( $oIp->getRequestIp() ) ) {
			$aWarnings[] = __( 'Traffic Watcher will not run because visitor IP address detection is not correctly configured.', 'wp-simple-firewall' );
		}

		switch ( $sSection ) {
			case 'section_traffic_limiter':
				if ( $this->isPremium() ) {
					if ( !$oOpts->isTrafficLoggerEnabled() ) {
						$aWarnings[] = sprintf( __( '%s may only be enabled if the Traffic Logger feature is also turned on.', 'wp-simple-firewall' ), __( 'Traffic Rate Limiter', 'wp-simple-firewall' ) );
					}
				}
				else {
					$aWarnings[] = sprintf( __( '%s is a Pro-only feature.', 'wp-simple-firewall' ), __( 'Traffic Rate Limiter', 'wp-simple-firewall' ) );
				}
				break;
		}

		return $aWarnings;
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() {
		return 'Traffic';
	}
}