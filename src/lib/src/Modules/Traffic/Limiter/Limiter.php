<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Limiter;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class Limiter {

	use Shield\Modules\ModConsumer;

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_Traffic $oMod */
		$oMod = $this->getMod();

		try {
			$bAllowed = ( new TestIp() )
				->setMod( $oMod )
				->runTest( Services::IP()->getRequestIp() );
		}
		catch ( \Exception $oE ) {
			$bAllowed = false;
			/** @var Shield\Modules\Traffic\Options $oOpts */
			$oOpts = $oMod->getOptions();
			$this->getCon()->fireEvent(
				'request_limit_exceeded',
				[
					'audit' => [
						'count' => $oOpts->getLimitRequestCount(),
						'span'  => $oOpts->getLimitTimeSpan(),
					]
				]
			);
		}

		return $bAllowed;
	}
}