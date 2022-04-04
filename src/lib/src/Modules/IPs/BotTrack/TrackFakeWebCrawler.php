<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class TrackFakeWebCrawler extends Base {

	const OPT_KEY = 'track_fakewebcrawler';

	private $crawlerUsed = '';

	protected function process() {
	}

	private function identifiesAsCrawler() :bool {
		$identifiesAsCrawler = false;

		$userAgent = Services::Request()->getUserAgent();
		if ( !empty( $userAgent ) ) {
			foreach ( Services::ServiceProviders()->getAllCrawlerUseragents() as $possibleAgent ) {
				if ( stripos( $userAgent, $possibleAgent ) !== false ) {
					$identifiesAsCrawler = true;
					$this->crawlerUsed = $possibleAgent;
					break;
				}
			}
		}

		return $identifiesAsCrawler;
	}

	protected function getAuditData() :array {
		return array_merge( parent::getAuditData(), [
			'crawler' => $this->crawlerUsed
		] );
	}
}
