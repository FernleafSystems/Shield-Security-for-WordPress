<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\UserAgent;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

/**
 * You're a fake web crawler/bot is you identify as-such, but you're not a trusted/verified bot.
 */
class IsFakeWebCrawler extends Base {

	use RequestIP;
	use UserAgent;

	const SLUG = 'is_fake_web_crawler';

	protected function execConditionCheck() :bool {

		$uaMatch = ( new MatchUserAgent() )->setCon( $this->getCon() );
		$uaMatch->request_useragent = $this->getUserAgent();
		$uaMatch->match_useragents = Services::ServiceProviders()->getAllCrawlerUseragents();

		$isTrustedBot = ( new IsTrustedBot() )->setCon( $this->getCon() );
		$isTrustedBot->request_ip = $this->getRequestIP();
		$isTrustedBot->request_useragent = $this->getUserAgent();

		$detected = $uaMatch->run() && !$isTrustedBot->run();

		$this->conditionTriggerMeta = array_merge( $uaMatch->getConditionTriggerMetaData(), $isTrustedBot->getConditionTriggerMetaData() );
		return $detected;
	}
}