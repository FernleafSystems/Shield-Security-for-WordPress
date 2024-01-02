<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};

class MatchRequestPath extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_path';

	protected function execConditionCheck() :bool {
		$this->addConditionTriggerMeta( 'path', $this->req->path );
		return ( new Utility\PerformConditionMatch( $this->req->path, $this->p->match_path, $this->p->match_type ) )->doMatch();
	}

	public function getDescription() :string {
		return __( 'Does the request path match the given path.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_type' => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => Enum\EnumMatchTypes::MatchTypesForStrings(),
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_path' => [
				'type'  => Enum\EnumParameters::TYPE_STRING,
				'label' => __( 'Path To Match', 'wp-simple-firewall' ),
			],
		];
	}
}