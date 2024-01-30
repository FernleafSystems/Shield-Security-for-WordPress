<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;
use FernleafSystems\Wordpress\Services\Services;

class MatchUsername extends Base {

	use Traits\TypeUser;

	protected function execConditionCheck() :bool {
		$user = Services::WpUsers()->getCurrentWpUser();
		return $user instanceof \WP_User
			   && ( new PerformConditionMatch( $user->user_login, $this->p->match_username, $this->p->match_type ) )->doMatch();
	}

	public function getDescription() :string {
		return __( "Match the username for currently logged-in user.", 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_type'     => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => [
					Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
					Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
					Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
				],
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_username' => [
				'type'  => Enum\EnumParameters::TYPE_STRING,
				'label' => __( 'Match Username', 'wp-simple-firewall' ),
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