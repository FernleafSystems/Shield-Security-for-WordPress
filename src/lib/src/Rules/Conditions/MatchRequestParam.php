<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\PathsToMatchUnavailableException;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property bool     $is_match_regex
 * @property string   $match_category
 * @property string[] $match_patterns
 * @property array    $excluded_params
 */
class MatchRequestParam extends Base {

	public const SLUG = 'match_request_param';

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_patterns ) ) {
			throw new PathsToMatchUnavailableException();
		}

		$matched = false;
		foreach ( $this->getFinalRequestParamsToTest() as $param => $value ) {

			if ( empty( $value ) || !\is_string( $value ) ) {
				continue;
			}

			foreach ( $this->match_patterns as $matchPattern ) {

				$matched = ( $this->is_match_regex && \preg_match( sprintf( '#%s#i', $matchPattern ), $value ) )
						   || ( !$this->is_match_regex && ( stripos( $value, $matchPattern ) !== false ) );

				if ( $matched ) {
					$this->addConditionTriggerMeta( 'match_pattern', $matchPattern );
					$this->addConditionTriggerMeta( 'match_request_param', $param );
					$this->addConditionTriggerMeta( 'match_request_value', $value );
					$this->addConditionTriggerMeta( 'match_type', $this->is_match_regex ? 'regex' : 'simple' );
					$this->addConditionTriggerMeta( 'match_category', $this->match_category ?? 'unset' );
					break 2;
				}
			}
		}
		return $matched;
	}

	protected function getFinalRequestParamsToTest() :array {
		$finalParams = $this->getRequestParamsToTest();
		$allExcluded = \is_array( $this->excluded_params ) ? $this->excluded_params : [];

		$allPagesExclusions = $allExcluded[ '*' ] ?? [];

		$finalParams = \array_diff_key( $finalParams, \array_flip( $allPagesExclusions[ 'simple' ] ?? [] ) );

		if ( !empty( $finalParams ) ) {
			foreach ( \array_keys( $finalParams ) as $paramKey ) {
				foreach ( $allPagesExclusions[ 'regex' ] ?? [] as $exclKeyRegex ) {
					// You can have numeric parameter keys in query: e.g. ?asdf=123&456&
					if ( \preg_match( sprintf( '#%s#i', $exclKeyRegex ), (string)$paramKey ) ) {
						unset( $finalParams[ $paramKey ] );
					}
				}
			}
		}

		if ( !empty( $finalParams ) ) {
			unset( $allExcluded[ '*' ] );
			$thePage = Services::Request()->getPath();
			foreach ( $allExcluded as $pageName => $pageParams ) {

				if ( \strpos( $thePage, $pageName ) !== false ) {

					if ( empty( $pageParams ) ) {
						$finalParams = [];
					}
					elseif ( !empty( $pageParams[ 'simple' ] ) ) {
						$finalParams = \array_diff_key( $finalParams, \array_flip( $pageParams[ 'simple' ] ) );
					}
					elseif ( !empty( $pageParams[ 'regex' ] ) ) {
						// TODO
					}
					break;
				}
			}
		}

		return $finalParams;
	}

	protected function getRequestParamsToTest() :array {
		return [];
	}
}