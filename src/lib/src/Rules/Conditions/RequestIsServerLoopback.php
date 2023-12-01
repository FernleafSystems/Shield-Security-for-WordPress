<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;
use FernleafSystems\Wordpress\Services\Services;

class RequestIsServerLoopback extends Base {

	public const SLUG = 'request_is_server_loopback';

	protected function getSubConditions() :array {
		return [
			'conditions' => MatchRequestIp::class,
			'params'    => [
				'match_ips' => Services::IP()->getServerPublicIPs(),
			],
		];
	}
}