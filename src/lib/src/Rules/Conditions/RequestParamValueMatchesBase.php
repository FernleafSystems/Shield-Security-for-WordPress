<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\{
	RequestParamNameUnavailableException,
	RequestParamValueMatchPatternUnavailableException
};

/**
 * @property string $param_name
 * @property string $match_pattern
 */
abstract class RequestParamValueMatchesBase extends Base {

	use Traits\TypeRequest;

	public function getDescription() :string {
		return __( 'Does the value of the given request parameter match the given pattern.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		if ( empty( $this->param_name ) ) {
			throw new RequestParamNameUnavailableException();
		}
		if ( empty( $this->match_pattern ) ) {
			throw new RequestParamValueMatchPatternUnavailableException();
		}

		$value = $this->getRequestParamValue();
		$this->addConditionTriggerMeta( 'match_pattern', $this->match_pattern );
		$this->addConditionTriggerMeta( 'match_request_param', $this->param_name );
		$this->addConditionTriggerMeta( 'match_request_value', $value );

		return (bool)\preg_match( sprintf( '#%s#i', $this->match_pattern ), $value );
	}

	protected function getRequestParamValue() :string {
		return '';
	}

	public function getParamsDef() :array {
		return [
			'param_name'    => [
				'type'  => 'string',
				'label' => __( 'Parameter Name', 'wp-simple-firewall' ),
			],
			'match_pattern' => [
				'type'  => 'string',
				'label' => __( 'Match Parameter Value Pattern', 'wp-simple-firewall' ),
			],
		];
	}
}