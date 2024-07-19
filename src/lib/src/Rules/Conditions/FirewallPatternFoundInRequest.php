<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Enum,
	Utility\PerformConditionMatch
};

class FirewallPatternFoundInRequest extends Base {

	use Traits\TypeRequest;

	private static $ParamsToAssess = null;

	protected function execConditionCheck() :bool {
		$matched = false;
		foreach ( $this->getParamsToAssess() as $param => $value ) {
			if ( \is_string( $value ) && ( new PerformConditionMatch( $value, $this->p->pattern, $this->p->match_type ) )->doMatch() ) {
				$category = $this->findCategoryFromPattern( $this->p->pattern );
				$this->addConditionTriggerMeta( 'match_name', $this->getFirewallRuleNameFromCategory( $category ) );
				$this->addConditionTriggerMeta( 'match_category', $category );
				$this->addConditionTriggerMeta( 'match_pattern', $this->p->pattern );
				$this->addConditionTriggerMeta( 'match_request_param', $param );
				$this->addConditionTriggerMeta( 'match_request_value', $value );
				$this->addConditionTriggerMeta( 'match_type', 'regex' );
				$matched = true;
				break;
			}
		}
		return $matched;
	}

	private function getFirewallRuleNameFromCategory( string $category ) :string {
		try {
			$ruleName = ( new StringsOptions() )->getFor( 'block_'.$category )[ 'name' ];
		}
		catch ( \Exception $e ) {
			$ruleName = 'Unknown';
		}
		return $ruleName;
	}

	private function findCategoryFromPattern( string $pattern ) :string {
		$category = '';
		foreach ( self::con()->cfg->configuration->def( 'firewall_patterns' ) as $cat => $group ) {
			if ( \in_array( $pattern, $group ) ) {
				$category = $cat;
				break;
			}
		}
		return $category;
	}

	private function getParamsToAssess() :array {
		if ( self::$ParamsToAssess === null ) {
			self::$ParamsToAssess = [];
			foreach ( \array_merge( $this->req->request->query, $this->req->request->post ) as $param => $value ) {
				if ( !empty( $value ) && \is_string( $value ) ) {
					$param = (string)$param;
					if ( !$this->isParameterExcluded( $param ) ) {
						self::$ParamsToAssess[ $param ] = $value;
					}
				}
			}
		}
		return self::$ParamsToAssess;
	}

	private function isParameterExcluded( string $param ) :bool {
		$excluded = false;
		foreach ( $this->getAllParameterExclusions() as $path => $excludedParams ) {
			if ( $path === '*' || \str_contains( $this->req->path, $path ) ) {
				foreach ( $excludedParams as $excludedParamRegex ) {
					if ( \preg_match( $excludedParamRegex, $param ) ) {
						$excluded = true;
						break( 2 );
					}
				}
			}
		}
		return $excluded;
	}

	private function getAllParameterExclusions() :array {
		$exclusions = self::con()->cfg->configuration->def( 'default_whitelist' );
		foreach ( self::con()->comps->opts_lookup->getFirewallParametersWhitelist() as $page => $params ) {
			if ( !empty( $params ) && \is_array( $params ) ) {
				$exclusions[ $page ] = \array_merge(
					$exclusions[ $page ] ?? [],
					\array_map(
						function ( $param ) {
							return sprintf( '#^%s$#i', \preg_quote( $param, '#' ) );
						},
						$params
					)
				);
			}
		}
		return $exclusions;
	}

	public function getDescription() :string {
		return __( 'Do any parameters in the request match the given set of firewall rule patterns.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'pattern'    => [
				'type'  => Enum\EnumParameters::TYPE_SCALAR,
				'label' => __( 'Parameter Name', 'wp-simple-firewall' ),
			],
			'match_type' => [
				'type'    => Enum\EnumParameters::TYPE_STRING,
				'default' => Enum\EnumMatchTypes::MATCH_TYPE_REGEX,
				'label'   => __( 'Parameter Value', 'wp-simple-firewall' ),
			],
		];
	}
}