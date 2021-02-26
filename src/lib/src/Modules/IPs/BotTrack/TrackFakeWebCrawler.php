<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class TrackFakeWebCrawler
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack
 */
class TrackFakeWebCrawler extends Base {

	const OPT_KEY = 'track_fakewebcrawler';

	private $agentUsed = '';

	protected function process() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		if ( $this->identifiesAsCrawler() && !$mod->isVerifiedBot() ) {
			$this->doTransgression();
		}
	}

	private function identifiesAsCrawler() :bool {
		$identifiesAsCrawler = false;

		$userAgent = Services::Request()->getUserAgent();
		if ( !empty( $userAgent ) ) {
			foreach ( Services::ServiceProviders()->getAllCrawlerUseragents() as $possibleAgent ) {
				if ( stripos( $userAgent, $possibleAgent ) !== false ) {
					$identifiesAsCrawler = true;
					$this->agentUsed = $possibleAgent;
					break;
				}
			}
		}

		return $identifiesAsCrawler;
	}

	protected function getAuditData() :array {
		return [
			'script' => $this->agentUsed
		];
	}
}
