<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Services\Services;

/**
 * Class TrackFakeWebCrawler
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack
 */
class TrackFakeWebCrawler extends Base {

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
	 * @return $this
	 */
	protected function getAuditMsg() {
		return sprintf( _wpsf__( 'Fake Web Crawler detected at "%s"' ), Services::Request()->getPath() );
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
