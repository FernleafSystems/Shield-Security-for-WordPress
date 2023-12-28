<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\RequestParamValueMatchPatternUnavailableException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\PerformConditionMatch;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $param_source
 * @property string $match_type
 * @property string $match_pattern
 */
class RequestParameterExists extends Base {

	use Traits\TypeRequest;

	public function getDescription() :string {
		return __( 'Does the request contain a parameter with the provide name.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		if ( empty( $this->match_pattern ) ) {
			throw new RequestParamValueMatchPatternUnavailableException();
		}

		$this->addConditionTriggerMeta( 'match_pattern', $this->match_pattern );

		$matches = false;

		$paramSources = [];
		if ( \str_contains( $this->param_source, 'get' ) ) {
			$paramSources[] = Services::Request()->query;
		}
		if ( \str_contains( $this->param_source, 'post' ) ) {
			$paramSources[] = Services::Request()->post;
		}

		foreach ( \array_map( '\array_keys', $paramSources ) as $paramSource ) {
			foreach ( $paramSource as $paramName ) {
				if ( ( new PerformConditionMatch( $paramName, $this->match_pattern, $this->match_type ) )->doMatch() ) {
					$matches = true;
					break;
				}
			}
		}

		return $matches;
	}

	protected function getParams() :array {
		return [];
	}

	public function getParamsDef() :array {
		$sources = [
			'get'      => '$_GET',
			'post'     => '$_POST',
			'get_post' => '$_GET & $_POST',
		];
		return [
			'param_source'  => [
				'type'        => EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $sources ),
				'enum_labels' => $sources,
				'default'     => 'get_post',
				'label'       => __( 'Which Parameters To Check', 'wp-simple-firewall' ),
			],
			'match_type'    => [
				'type'      => EnumParameters::TYPE_ENUM,
				'type_enum' => EnumMatchTypes::MatchTypesForStrings(),
				'default'   => EnumMatchTypes::MATCH_TYPE_EQUALS_I,
				'label'     => __( 'Match Type', 'wp-simple-firewall' ),
			],
			'match_pattern' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Compare Parameter Name To', 'wp-simple-firewall' ),
			],
		];
	}
}