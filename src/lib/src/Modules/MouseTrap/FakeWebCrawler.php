<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\MouseTrap;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class FakeWebCrawler
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\MouseTrap
 */
class FakeWebCrawler extends Base {

	protected function process() {
		/** @var \ICWP_WPSF_FeatureHandler_Mousetrap $oFO */
		$oFO = $this->getMod();

		if ( $this->getIfVisitorIdentifiesAsCrawler() && !$oFO->isVerifiedBot() ) {

			if ( $oFO->isTransgression404() ) {
				$oFO->setIpTransgressed();
			}
			else {
				$oFO->setIpBlocked();
			}

			$this->createNewAudit(
				'wpsf',
				sprintf( '%s: %s', _wpsf__( 'MouseTrap' ), _wpsf__( 'Fake web crawler detected' ) ),
				2, 'mousetrap_fakewebcrawler'
			);
		}
	}

	/**
	 * @return bool
	 */
	private function getIfVisitorIdentifiesAsCrawler() {
		$bIdentifiesAs = false;

		return $bIdentifiesAs;
	}
}
