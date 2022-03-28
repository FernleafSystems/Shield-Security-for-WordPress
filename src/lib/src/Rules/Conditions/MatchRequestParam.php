<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\PathsToMatchUnavailableException;

/**
 * @property bool     $is_match_regex
 * @property string[] $match_patterns
 */
class MatchRequestParam extends Base {

	const SLUG = 'match_request_param';

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_patterns ) ) {
			throw new PathsToMatchUnavailableException();
		}

		$matched = false;
		$requestParams = $this->getRequestParamsToTest();
		foreach ( $this->match_patterns as $matchPattern ) {

			foreach ( $requestParams as $param => $value ) {

				$matched = ( $this->is_match_regex && preg_match( sprintf( '#%s#i', $matchPattern ), $value ) )
						   || ( !$this->is_match_regex && stripos( $value, $matchPattern ) !== false );

				if ( $matched ) {
					$this->addConditionTriggerMeta( 'match_request_param', $param );
					$this->addConditionTriggerMeta( 'match_request_value', $value );
					$this->addConditionTriggerMeta( 'match_type', $this->is_match_regex ? 'regex' : 'simple' );
					$this->addConditionTriggerMeta( 'match_check', 'unset' ); // TODO: sql, etc.
				}
			}
		}
		return $matched;
	}

	protected function getRequestParamsToTest() :array {
		return [];
	}
}