<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class MatchRequestNotIpIdentity extends MatchRequestIpIdentity {

	public const SLUG = 'match_request_not_ip_identity';

	public function getName() :string {
		return __( "Does the current request NOT originate from a given set of services/providers.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		return !parent::execConditionCheck();
	}
}