<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits\RequestIP;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class IsTrustedBot extends Base {

	use RequestIP;

	const SLUG = 'is_trusted_bot';

	protected function execConditionCheck() :bool {
		$isLoopback = ( new IsServerLoopback() )->setCon( $this->getCon() );
		$isLoopback->request_ip = $this->getRequestIP();

		$idMatch = ( new MatchRequestIPIdentity() )->setCon( $this->getCon() );
		$idMatch->match_not_ip_ids = [
			IpID::UNKNOWN,
			IpID::THIS_SERVER,
			IpID::VISITOR,
		];

		$detected = !$isLoopback->run() && $idMatch->run();
		$this->conditionTriggerMeta = $isLoopback->getConditionTriggerMetaData();
		return $detected;
	}
}