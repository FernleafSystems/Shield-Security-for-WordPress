<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class IsFakeWebCrawler extends Base {

	const CONDITION_SLUG = 'is_fake_web_crawler';

	protected function execConditionCheck() :bool {

		$uaMatch = ( new MatchUserAgent() )->setCon( $this->getCon() );
		$uaMatch->match_useragents = Services::ServiceProviders()->getAllCrawlerUseragents();

		$idMatch = ( new MatchRequestIPIdentity() )->setCon( $this->getCon() );
		$idMatch->match_not_ip_ids = [
			IpID::UNKNOWN,
			IpID::THIS_SERVER,
			IpID::VISITOR,
		];

		$detected = $uaMatch->run() && $idMatch->run();

		$this->conditionTriggerMeta = array_merge( $uaMatch->getTriggerMetaData(), $idMatch->getTriggerMetaData() );
		return $detected;
	}
}