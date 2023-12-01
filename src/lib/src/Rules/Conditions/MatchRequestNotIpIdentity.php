<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class MatchRequestNotIpIdentity extends MatchRequestIpIdentity {

	public const SLUG = 'match_request_not_ip_identity';

	protected function execConditionCheck() :bool {
		return !parent::execConditionCheck();
	}
}