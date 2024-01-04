<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};

class PhpDefineIs extends Base {

	use Traits\TypePhp;

	public const SLUG = 'php_define_is';

	protected function execConditionCheck() :bool {
		return \defined( $this->p->name )
			   &&
			   ( new Utility\PerformConditionMatch(
				   \constant( $this->p->name ),
				   $this->p->match_value,
				   $this->p->match_type
			   ) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the request path match the given path.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'name'        => [
				'type'  => Enum\EnumParameters::TYPE_STRING,
				'label' => __( 'Define Name', 'wp-simple-firewall' ),
			],
			'match_type'  => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => Enum\EnumMatchTypes::MatchTypesForStrings(),
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
				'for_param' => 'match_value',
			],
			'match_value' => [
				'type'  => Enum\EnumParameters::TYPE_SCALAR,
				'label' => __( 'Define Value To Match', 'wp-simple-firewall' ),
			],
		];
	}
}