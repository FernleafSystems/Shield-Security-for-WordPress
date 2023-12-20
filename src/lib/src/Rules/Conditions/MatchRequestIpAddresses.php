<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

/**
 * @property string   $match_type
 * @property string[] $match_ips
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
							'match_type' => $this->match_type,
						],
					];
				},
				$this->match_ips
			),
		];
	}

	public function getParamsDef() :array {
		return [
			'match_ips'  => [
				'type'  => EnumParameters::TYPE_ARRAY,
				'label' => __( 'IP Addresses To Match', 'wp-simple-firewall' ),
			],
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