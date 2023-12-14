<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\PathsToMatchUnavailableException;

/**
 * @deprecated 18.5.8
 */
abstract class RequestParamIs extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'request_param_is';

	public function getDescription() :string {
		return __( 'Does the value of the given request parameter match against the given patterns.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_patterns ) ) {
			throw new PathsToMatchUnavailableException();
		}
		if ( empty( $this->match_param ) ) {
			throw new PathsToMatchUnavailableException();
		}

		$matched = false;

		$value = $this->getRequestParamValue();
		if ( \is_string( $value ) ) {
			foreach ( $this->match_patterns as $matchPattern ) {

				if ( \preg_match( sprintf( '#%s#i', $matchPattern ), $value ) ) {
					$matched = true;
					$this->addConditionTriggerMeta( 'match_pattern', $matchPattern );
					$this->addConditionTriggerMeta( 'match_request_param', $this->match_param );
					$this->addConditionTriggerMeta( 'match_request_value', $value );
					break;
				}
			}
		}

		return $matched;
	}

	/**
	 * @return mixed|null
	 */
	protected function getRequestParamValue() {
		return null;
	}

	public function getParamsDef() :array {
		return [
			'match_param'    => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Match Parameter Name', 'wp-simple-firewall' ),
			],
			'match_patterns' => [
				'type'  => EnumParameters::TYPE_ARRAY,
				'label' => __( 'Match Parameter Value Pattern', 'wp-simple-firewall' ),
			],
		];
	}
}