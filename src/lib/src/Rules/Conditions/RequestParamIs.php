<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\PathsToMatchUnavailableException;

/**
 * @property string   $match_param
 * @property string[] $match_patterns
 */
class RequestParamIs extends Base {

	public const SLUG = 'request_param_is';

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_patterns ) ) {
			throw new PathsToMatchUnavailableException();
		}
		if ( empty( $this->match_param ) ) {
			throw new PathsToMatchUnavailableException();
		}

		$matched = false;

		$value = $this->getRequestParamValue();
		if ( is_string( $value ) ) {
			foreach ( $this->match_patterns as $matchPattern ) {

				if ( preg_match( sprintf( '#%s#i', $matchPattern ), $value ) ) {
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
}