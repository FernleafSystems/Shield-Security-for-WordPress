<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Limit;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModCon;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class TestIp
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Limit
 */
class TestIp {

	use Shield\Modules\ModConsumer;

	/**
	 * @param string $sHumanIp
	 * @return bool  - true if request is allowed (i.e. request limit has not been exceeded)
	 * @throws \Exception
	 */
	public function runTest( $sHumanIp ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Shield\Modules\Traffic\Options $opts */
		$opts = $this->getOptions();

		$oNow = Services::Request()->carbon();

		/** @var Traffic\Select $oSel */
		$oSel = $mod->getDbHandler_Traffic()->getQuerySelector();
		$count = $oSel->filterByIp( inet_pton( $sHumanIp ) )
					  ->filterByCreatedAt( $oNow->subSeconds( $opts->getLimitTimeSpan() )->timestamp, '>' )
					  ->count();

		if ( $count > $opts->getLimitRequestCount() ) {
			throw new \Exception( 'Requests from IP have exceeded allowable limit.' );
		}

		return true;
	}
}