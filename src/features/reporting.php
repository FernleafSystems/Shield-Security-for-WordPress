<?php

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

/**
 * Class ICWP_WPSF_FeatureHandler_Reporting
 * @deprecated 10.1
 */
class ICWP_WPSF_FeatureHandler_Reporting extends ICWP_WPSF_FeatureHandler_BaseWpsf {

	/**
	 * @var Reporting\Lib\ReportingController
	 */
	private $oReportsController;

	/**
	 * @return Shield\Databases\Reports\Handler
	 */
	public function getDbHandler_Reports() {
		return $this->getDbH( 'reports' );
	}

	/**
	 * @return string
	 */
	protected function getNamespaceBase() :string {
		return 'Reporting';
	}

	/**
	 * @return Reporting\Lib\ReportingController
	 */
	public function getReportingController() {
		if ( !isset( $this->oReportsController ) ) {
			$this->oReportsController = ( new Reporting\Lib\ReportingController() )->setMod( $this );
		}
		return $this->oReportsController;
	}

	/**
	 * @return Reporting\Lib\ReportingController
	 */
	public function getProcessor() {
		return $this->getReportingController();
	}
}