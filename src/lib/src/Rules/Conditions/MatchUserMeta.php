<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};
use FernleafSystems\Wordpress\Services\Services;

class MatchUserMeta extends Base {

	use Traits\TypeUser;

	protected function execConditionCheck() :bool {
		$id = Services::WpUsers()->getCurrentWpUserId();
		return !empty( $id )
			   &&
			   ( new Utility\PerformConditionMatch(
				   get_user_meta( $id, $this->p->key, true ),
				   $this->p->match_value,
				   $this->p->match_type
			   ) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the request path match the given path.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'key'         => [
				'type'         => Enum\EnumParameters::TYPE_STRING,
				'label'        => __( 'Meta Key', 'wp-simple-firewall' ),
				'verify_regex' => '/^[a-zA-Z0-9_-]+$/'
			],
			'match_type'  => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => [
					Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
					Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
					Enum\EnumMatchTypes::MATCH_TYPE_LESS_THAN,
					Enum\EnumMatchTypes::MATCH_TYPE_GREATER_THAN,
					Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
				],
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
				'for_param' => 'match_value',
			],
			'match_value' => [
				'type'  => Enum\EnumParameters::TYPE_SCALAR,
				'label' => __( 'Meta Value To Match', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getSubConditions() :array {
		return [
			'logic'      => Enum\EnumLogic::LOGIC_AND,
			'conditions' => [
				[
					'conditions' => IsLoggedInNormal::class,
				],
				[
					'conditions' => $this->getDefaultConditionCheckCallable(),
				],
			]
		];
	}
}