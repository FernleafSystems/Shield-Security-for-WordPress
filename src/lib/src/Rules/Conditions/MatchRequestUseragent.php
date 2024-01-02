<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};

class MatchRequestUseragent extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_useragent';

	protected function execConditionCheck() :bool {
		$this->addConditionTriggerMeta( 'matched_useragent', $this->req->useragent );
		return ( new Utility\PerformConditionMatch(
			$this->req->useragent,
			$this->p->match_useragent,
			$this->p->match_type
		) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the request useragent match the given useragent.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_type'      => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => Enum\EnumMatchTypes::MatchTypesForStrings(),
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_CONTAINS_I,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_useragent' => [
				'type'  => Enum\EnumParameters::TYPE_STRING,
				'label' => __( 'Match Useragent', 'wp-simple-firewall' ),
			],
		];
	}
}