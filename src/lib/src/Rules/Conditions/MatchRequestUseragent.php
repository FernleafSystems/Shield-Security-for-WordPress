<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;

/**
 * @property string $match_type
 * @property string $match_useragent
 */
class MatchRequestUseragent extends Base {

	use Traits\TypeRequest;
	use Traits\UserAgent;

	public const SLUG = 'match_request_useragent';

	protected function execConditionCheck() :bool {
		$this->addConditionTriggerMeta( 'matched_useragent', $this->getUserAgent() );
		return ( new PerformConditionMatch( $this->getUserAgent(), $this->match_useragent, $this->match_type ) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the request useragent match the given useragent.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_type'      => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForStrings(),
				'default'   => EnumMatchTypes::MATCH_TYPE_CONTAINS_I,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_useragent' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Match Useragent', 'wp-simple-firewall' ),
			],
		];
	}
}