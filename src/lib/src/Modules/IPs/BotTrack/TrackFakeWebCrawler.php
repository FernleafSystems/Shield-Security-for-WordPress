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
		catch ( \Exception $e ) {
			$this->doTransgression();
		}
	}

	/**
	 * @return false
	 * @throws \Exception
	 */
	private function getIfVisitorIdentifiesAsCrawler() :bool {
		$identifiesAsCrawler = false;

		$userAgent = Services::Request()->getUserAgent();
		if ( !empty( $userAgent ) ) {
			foreach ( Services::ServiceProviders()->getAllCrawlerUseragents() as $possible ) {
				if ( stripos( $userAgent, $possible ) !== false ) {
					throw new \Exception( $possible );
				}
			}
		}

		return $identifiesAsCrawler;
	}
}
