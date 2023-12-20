<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumMatchTypes,
	EnumParameters
};

/**
 * @property string   $match_type
 * @property string[] $match_ip_ids
 */
class MatchRequestIpIdentities extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_ip_identities';

	public function getDescription() :string {
		return __( "Does the current request originate from a given set of services/providers.", 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_OR,
			'conditions' => \array_map(
				function ( $id ) {
					return [
						'conditions' => MatchRequestIpIdentity::class,
						'params'     => [
							'match_type'  => $this->match_type,
							'match_ip_id' => $id,
						],
					];
				},
				$this->match_ip_ids
			),
		];
	}

	public function getParamsDef() :array {
		return [
			'match_type'   => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForStrings(),
				'default'   => EnumMatchTypes::MATCH_TYPE_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_ip_ids' => [
				'type'  => EnumParameters::TYPE_ARRAY,
				'label' => __( 'IP IDs To Match', 'wp-simple-firewall' ),
			],
		];
	}
}