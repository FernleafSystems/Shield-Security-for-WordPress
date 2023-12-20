<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

/**
 * @property string   $match_type
 * @property string[] $match_script_names
 */
class MatchRequestScriptNames extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_script_names';

	public function getDescription() :string {
		return __( 'Does the request script name match the given set of names.', 'wp-simple-firewall' );
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => EnumLogic::LOGIC_OR,
			'conditions' => \array_map(
				function ( $name ) {
					return [
						'conditions' => MatchRequestScriptName::class,
						'params'     => [
							'match_type'        => $this->match_type,
							'match_script_name' => $name,
						],
					];
				},
				$this->match_script_names
			),
		];
	}

	public function getParamsDef() :array {
		return [
			'match_type'         => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForStrings(),
				'default'   => EnumMatchTypes::MATCH_TYPE_REGEX,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_script_names' => [
				'type'  => EnumParameters::TYPE_ARRAY,
				'label' => __( 'Match Script Names', 'wp-simple-firewall' ),
			],
		];
	}
}