<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Limiter;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class TestIp
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Limiter
 */
class TestIp {

	use Shield\Modules\ModConsumer;

	/**
	 * @param string $sHumanIp
	 * @return bool  - true if request is allowed (i.e. request limit has not been exceeded)
	 * @throws \Exception
	 */
	public function runTest( $sHumanIp ) {
		/** @var \ICWP_WPSF_FeatureHandler_Traffic $oMod */
		$oMod = $this->getMod();
		/** @var Shield\Modules\Traffic\Options $oOpts */
		$oOpts = $oMod->getOptions();
		/** @var Traffic\Select $oSel */
		$oSel = $oMod->getDbHandler()->getQuerySelector();

		$oNow = Services::Request()->carbon();

		$nCount = $oSel->filterByIp( inet_pton( $sHumanIp ) )
					   ->filterByCreatedAt( $oNow->subSeconds( $oOpts->getLimitTimeSpan() )->timestamp, '>' )
					   ->count();

		if ( $nCount > $oOpts->getLimitRequestCount() ) {
			throw new \Exception( 'Requests from IP have exceeded allowable limit.' );
		}

		return true;
	}
}