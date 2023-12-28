<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\PathsToMatchUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string   $match_type
 * @property string   $match_category
 * @property string[] $match_patterns
 * @property array    $excluded_params
 */
class MatchRequestParam extends Base {

	use Traits\TypeRequest;

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

				$matched = ( new PerformConditionMatch( $value, $matchPattern, $this->match_type ) )->doMatch();

				if ( $matched ) {
					$this->addConditionTriggerMeta( 'match_pattern', $matchPattern );
					$this->addConditionTriggerMeta( 'match_request_param', $param );
					$this->addConditionTriggerMeta( 'match_request_value', $value );
					$this->addConditionTriggerMeta( 'match_type', $this->match_type === EnumMatchTypes::MATCH_TYPE_REGEX ? 'regex' : 'simple' );
					$this->addConditionTriggerMeta( 'match_category', $this->match_category ?? 'unset' );
					break 2;
				}
			}
		}
		return $matched;
	}

	public function getDescription() :string {
		return __( "Do any parameters in the request match the given set of parameters to test.", 'wp-simple-firewall' );
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

	public function getParamsDef() :array {
		return [
			'match_type'      => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForStrings(),
				'default'   => EnumMatchTypes::MATCH_TYPE_REGEX,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_patterns'  => [
				'type'  => EnumParameters::TYPE_ARRAY,
				'label' => __( 'Match Patterns', 'wp-simple-firewall' ),
			],
			'match_category'  => [
				'type'    => EnumParameters::TYPE_STRING,
				'label'   => __( 'Match Category', 'wp-simple-firewall' ),
				'default' => '',
			],
			'excluded_params' => [
				'type'    => EnumParameters::TYPE_ARRAY,
				'label'   => __( 'Excluded Parameters', 'wp-simple-firewall' ),
				'default' => [],
			],
		];
	}

	protected function getRequestParamsToTest() :array {
		return [];
	}
}