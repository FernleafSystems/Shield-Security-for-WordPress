<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class RequestIsServerLoopback extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'request_is_server_loopback';

	public function getDescription() :string {
		return __( 'Is the request a server loopback request.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'conditions' => MatchRequestIpAddresses::class,
			'params'     => [
				'match_ips' => Services::IP()->getServerPublicIPs(),
			],
		];
	}
}