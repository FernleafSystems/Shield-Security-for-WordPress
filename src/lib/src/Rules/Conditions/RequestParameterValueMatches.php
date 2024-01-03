<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility
};

class RequestParameterValueMatches extends Base {

	use Traits\TypeRequest;

	protected function execConditionCheck() :bool {
		if ( $this->p->req_param_source === 'get' ) {
			$value = $this->req->query[ $this->p->param_name ] ?? null;
		}
		elseif ( $this->p->req_param_source === 'post' ) {
			$value = $this->req->post[ $this->p->param_name ] ?? null;
		}
		elseif ( $this->p->req_param_source === 'cookie' ) {
			$value = $this->req->cookies[ $this->p->param_name ] ?? null;
		}
		elseif ( $this->p->req_param_source === 'header' ) {
			$value = $this->req->headers[ $this->p->param_name ] ?? null;
		}
		else {
			$value = null;
		}

		$isMatch = false;
		if ( $value !== null ) {
			$isMatch = ( new Utility\PerformConditionMatch( $value, $this->p->match_pattern, $this->p->match_type ) )->doMatch();
			$this->addConditionTriggerMeta( 'match_pattern', $this->p->match_pattern );
			$this->addConditionTriggerMeta( 'match_request_param', $this->p->param_name );
			$this->addConditionTriggerMeta( 'match_request_value', $value );
		}
		return $isMatch;
	}

	public function getDescription() :string {
		return __( 'Does the value of the given request parameter match the given pattern.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		$sources = [
			'get'    => '$_GET',
			'post'   => '$_POST',
			'cookie' => '$_COOKIE',
			'header' => 'HTTP Header',
		];
		return [
			'req_param_source' => [
				'type'        => Enum\EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $sources ),
				'enum_labels' => $sources,
				'default'     => 'get',
				'label'       => __( 'Which Parameters To Check', 'wp-simple-firewall' ),
			],
			'param_name'       => [
				'type'  => Enum\EnumParameters::TYPE_STRING,
				'label' => __( 'Parameter Name', 'wp-simple-firewall' ),
			],
			'match_type'       => [
				'type'      => Enum\EnumParameters::TYPE_ENUM,
				'type_enum' => Enum\EnumMatchTypes::MatchTypesForStrings(),
				'default'   => Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_pattern'    => [
				'type'  => Enum\EnumParameters::TYPE_STRING,
				'label' => __( 'Compare Parameter Value To', 'wp-simple-firewall' ),
			],
		];
	}
}