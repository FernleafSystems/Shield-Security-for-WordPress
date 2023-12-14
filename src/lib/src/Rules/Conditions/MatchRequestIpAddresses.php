<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;

/**
 * @property string[] $match_ips
 */
class MatchRequestIpAddresses extends Base {

	use Traits\RequestIP;
	use Traits\TypeRequest;

	public const SLUG = 'match_request_ip_addresses';

	public function getDescription() :string {
		return __( 'Does the current request originate from a given set of IP Addresses.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Constants::LOGIC_OR,
			'conditions' => \array_map(
				function ( $ip ) {
					return [
						'conditions' => MatchRequestIpAddress::class,
						'params'     => [
							'match_ip' => $ip,
						],
					];
				},
				$this->match_ips
			),
		];
	}

	public function getParamsDef() :array {
		return [
			'match_ip' => [
				'type'  => 'array',
				'label' => __( 'IP Addresses To Match', 'wp-simple-firewall' ),
			],
		];
	}
}