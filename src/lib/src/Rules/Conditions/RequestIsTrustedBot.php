<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class RequestIsTrustedBot extends Base {

	public const SLUG = 'request_is_trusted_bot';

	public function getName() :string {
		return __( 'Is the request a bot that originates from a trusted service provider.', 'wp-simple-firewall' );
	}

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->is_trusted_bot;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->is_trusted_bot = $result;
	}

	protected function getSubConditions() :array {
		return [
			'conditions' => MatchRequestNotIpIdentity::class,
			'params'    => [
				'match_ip_ids' => (array)apply_filters( 'shield/untrusted_service_providers', [
					IpID::UNKNOWN,
					IpID::THIS_SERVER,
					IpID::VISITOR,
				] ),
			],
		];
	}
}