<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;
use FernleafSystems\Wordpress\Services\Services;

class IsUserId extends Base {

	use Traits\TypeUser;

	protected function execConditionCheck() :bool {
		$user = Services::WpUsers()->getCurrentWpUser();
		return $user instanceof \WP_User
			   && ( new PerformConditionMatch( $user->ID, $this->p->match_userid, $this->p->match_type ) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the ID of the currently logged-in user equal that provided.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_type'   => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => [
					Enum\EnumMatchTypes::MATCH_TYPE_EQUALS,
				],
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS_I,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_userid' => [
				'type'  => Enum\EnumParameters::TYPE_INT,
				'label' => __( 'Match User ID', 'wp-simple-firewall' ),
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