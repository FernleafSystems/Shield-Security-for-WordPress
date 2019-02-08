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

		try {
			$this->getIfVisitorIdentifiesAsCrawler();
		}
		catch ( \Exception $oE ) {
			if ( !$oFO->isVerifiedBot() ) {

				$oFO->isTransgression404() ? $oFO->setIpTransgressed() : $oFO->setIpBlocked();

				$this->createNewAudit(
					'wpsf',
					sprintf( '%s: %s', _wpsf__( 'MouseTrap' ),
						sprintf( _wpsf__( 'Fake web crawler detected- "%s" ' ), $oE->getMessage() ) ),
					2, 'mousetrap_fakewebcrawler'
				);
			}
		}
	}

	/**
	 * @return false
	 * @throws \Exception
	 */
	private function getIfVisitorIdentifiesAsCrawler() {
		$bIdentifiesAs = false;

		$sUserAgent = Services::Request()->getUserAgent();
		if ( !empty( $sUserAgent ) ) {
			foreach ( Services::ServiceProviders()->getAllCrawlerUseragents() as $sPossibleAgent ) {
				if ( stripos( $sUserAgent, $sPossibleAgent ) !== false ) {
					throw new \Exception( $sPossibleAgent );
					break;
				}
			}
		}

		return $bIdentifiesAs;
	}
}
