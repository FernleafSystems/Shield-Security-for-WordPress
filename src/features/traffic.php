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
	 * @return string
	 */
	protected function getNamespaceBase() :string {
		return 'Traffic';
	}
}