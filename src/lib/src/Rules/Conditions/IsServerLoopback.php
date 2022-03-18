<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Services\Services;

class IsServerLoopback extends Base {

	use RequestIP;

	const CONDITION_SLUG = 'is_server_loopback';

	protected function execConditionCheck() :bool {
		$ipMatch = ( new MatchRequestIP() )->setCon( $this->getCon() );
		$ipMatch->request_ip = $this->getRequestIP();
		$ipMatch->match_ips = Services::IP()->getServerPublicIPs();

		$detected = $ipMatch->run();
		$this->conditionTriggerMeta = $ipMatch->getTriggerMetaData();
		return $detected;
	}
}