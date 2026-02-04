<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Conditions,
	Enum
};
use FernleafSystems\Wordpress\Services\Services;

class RequestIsServerLoopback extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'request_is_server_loopback';

	public function getDescription() :string {
		return __( 'Is the request a server loopback request.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_OR,
			'conditions' => \array_map(
				function ( $ip ) {
					return [
						'conditions' => Conditions\MatchRequestIpAddress::class,
						'params'     => [
							'match_ip'   => $ip,
							'match_type' => Enum\EnumMatchTypes::MATCH_TYPE_IP_EQUALS,
						],
					];
				},
				\array_merge( Services::IP()->getServerPublicIPs(), [
					'127.0.0.1',
					'::1'
				] )
			),
		];
	}
}