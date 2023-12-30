<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumMatchTypes,
	EnumParameters
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;

/**
 * @property string $define_name
 * @property string $match_type
 * @property string $match_value
 */
class PhpDefineIs extends Base {

	use Traits\TypePhp;

	public const SLUG = 'php_define_is';

	protected function execConditionCheck() :bool {
		return \defined( $this->define_name )
			   && ( new PerformConditionMatch( \constant( $this->define_name ), $this->match_value, $this->match_type ) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the request path match the given path.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'define_name' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Define Name', 'wp-simple-firewall' ),
			],
			'match_type'  => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForStrings(),
				'default'   => EnumMatchTypes::MATCH_TYPE_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_value' => [
				'type'  => EnumParameters::TYPE_SCALAR,
				'label' => __( 'Define Value To Match', 'wp-simple-firewall' ),
			],
		];
	}
}