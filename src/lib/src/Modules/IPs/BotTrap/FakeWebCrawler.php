<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrap;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class FakeWebCrawler
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrap
 */
class FakeWebCrawler extends Base {

	const OPT_KEY = 'track_fakewebcrawler';

	protected function process() {
		try {
			$this->getIfVisitorIdentifiesAsCrawler(); // TEST this logic
		}
		catch ( \Exception $oE ) {
			$this->doTransgression();
		}
	}

	/**
	 * @return bool
	 */
	protected function isTransgression() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oFO */
		$oFO = $this->getMod();
		return $oFO->isTransgressionFakeWebCrawler();
	}

	/**
	 * @return $this
	 */
	protected function writeAudit() {
		$this->createNewAudit(
			'wpsf',
			sprintf( _wpsf__( 'Fake Web Crawler detected at "%s"' ), Services::Request()->getPath() ),
			2, 'bottrap_fakecrawler'
		);
		return $this;
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
