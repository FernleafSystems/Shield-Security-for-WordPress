<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\{
	RequestParamNameUnavailableException,
	RequestParamValueMatchPatternUnavailableException
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;

/**
 * @property string $param_source
 * @property string $match_type
 * @property string $param_name
 * @property string $match_pattern
 */
class RequestParameterValueMatches extends Base {

	use Traits\TypeRequest;

	protected function execConditionCheck() :bool {
		if ( empty( $this->param_name ) ) {
			throw new RequestParamNameUnavailableException();
		}
		if ( empty( $this->match_pattern ) ) {
			throw new RequestParamValueMatchPatternUnavailableException();
		}

		if ( $this->param_source === 'get' ) {
			$value = Services::Request()->query( $this->param_name );
		}
		elseif ( $this->param_source === 'post' ) {
			$value = Services::Request()->post( $this->param_name );
		}
		elseif ( $this->param_source === 'cookie' ) {
			$value = Services::Request()->cookie( $this->param_name );
		}
		else {
			$value = null;
		}

		$matches = false;
		if ( $value !== null ) {
			$matches = ( new PerformConditionMatch( $value, $this->match_pattern, $this->match_type ) )->doMatch();
			$this->addConditionTriggerMeta( 'match_pattern', $this->match_pattern );
			$this->addConditionTriggerMeta( 'match_request_param', $this->param_name );
			$this->addConditionTriggerMeta( 'match_request_value', $value );
		}
		return $matches;
	}

	public function getDescription() :string {
		return __( 'Does the value of the given request parameter match the given pattern.', 'wp-simple-firewall' );
	}

	protected function getRequestParamValue() :string {
		return '';
	}

	public function getParamsDef() :array {
		$sources = [
			'get'    => '$_GET',
			'post'   => '$_POST',
			'cookie' => '$_COOKIE',
		];
		return [
			'param_source'  => [
				'type'        => EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $sources ),
				'enum_labels' => $sources,
				'default'     => 'get',
				'label'       => __( 'Which Parameters To Check', 'wp-simple-firewall' ),
			],
			'param_name'    => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Parameter Name', 'wp-simple-firewall' ),
			],
			'match_type'    => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForStrings(),
				'default'   => EnumMatchTypes::MATCH_TYPE_REGEX,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_pattern' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Compare Parameter Value To', 'wp-simple-firewall' ),
			],
		];
	}
}