<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};

class ShieldConfigurationOption extends Base {

	use Traits\TypeShield;

	public function getDescription() :string {
		return __( 'Is The Shield Option Value...', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$opts = self::con()->opts;
		return $opts->optExists( $this->p->name ) &&
			   ( new Utility\PerformConditionMatch(
				   $opts->optGet( $this->p->name ),
				   $this->p->match_value,
				   $this->p->match_type
			   ) )->doMatch();
	}

	public function getParamsDef() :array {
		return [
			'name'        => [
				'type'         => Enum\EnumParameters::TYPE_STRING,
				'label'        => __( 'Shield Option Key', 'wp-simple-firewall' ),
				'verify_regex' => '/^[a-zA-Z0-9_]+$/'
			],
			'match_type'  => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => [
					Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
					Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
					Enum\EnumMatchTypes::MATCH_TYPE_LESS_THAN,
					Enum\EnumMatchTypes::MATCH_TYPE_GREATER_THAN,
				],
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_value' => [
				'type'  => Enum\EnumParameters::TYPE_SCALAR,
				'label' => __( 'Value To Match', 'wp-simple-firewall' ),
			],
		];
	}
}