<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

/**
 * @deprecated 18.6
 */
class MatchRequestIpAddresses extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_ip_addresses';

	public function getDescription() :string {
		return __( 'Does the current request originate from a given set of IP Addresses.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_OR,
			'conditions' => \array_map(
				function ( $ip ) {
					return [
						'conditions' => MatchRequestIpAddress::class,
						'params'     => [
							'match_ip'   => $ip,
							'match_type' => $this->p->match_type,
						],
					];
				},
				[]
			),
		];
	}

	public function getParamsDef() :array {
		return [
			'match_type' => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => [
					EnumMatchTypes::MATCH_TYPE_IP_EQUALS,
					EnumMatchTypes::MATCH_TYPE_IP_RANGE,
				],
				'default'   => EnumMatchTypes::MATCH_TYPE_IP_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
		];
	}
}