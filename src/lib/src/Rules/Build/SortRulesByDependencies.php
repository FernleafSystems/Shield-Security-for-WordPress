<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\MutuallyDependentRulesException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\NoSuchConditionHandlerException;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;

class SortRulesByDependencies {

	use PluginControllerConsumer;

	/**
	 * @var RuleVO[]
	 */
	private $rules;

	private $dependencies = [];

	private $finalRulesOrder = [];

	public function __construct( array $rules ) {
		$this->rules = $rules;
	}

	/**
	 * @return RuleVO[]
	 * @throws MutuallyDependentRulesException
	 */
	public function run() :array {
		$this->buildDependencyGraph();
		$this->assignWpHooks();
		$this->verifyDependencies();
		$this->orderRules();
		return $this->rules;
	}

	/**
	 * @throws MutuallyDependentRulesException
	 */
	private function verifyDependencies() {
		// Ensure that there are no mutually dependent rules
		$rulesDependenciesMap = $this->filterDependenciesForRulesOnly();
		foreach ( $rulesDependenciesMap as $primaryRuleSlug => $dep ) {
			foreach ( $dep as $depRuleSlug ) {
				if ( \in_array( $primaryRuleSlug, $rulesDependenciesMap[ $depRuleSlug ] ) ) {
					throw new MutuallyDependentRulesException( sprintf( 'The rules "%s" and "%s" are mutually dependent.',
						$primaryRuleSlug, $depRuleSlug ) );
				}
			}
		}
	}

	private function filterDependenciesForRulesOnly() :array {
		return \array_map(
			function ( array $dependencies ) {
				return \array_intersect( $dependencies, \array_keys( $this->rules ) );
			},
			\array_intersect_key( $this->dependencies, $this->rules )
		);
	}

	private function orderRules() {
		// Filter out everything to only have rules (not conditions).
		$rulesDependenciesMap = $this->filterDependenciesForRulesOnly();

		$finalRulesOrder = [];

		$count = 0;
		do {
			$nextRule = $this->findRuleWithZeroDependencies( $rulesDependenciesMap );

			if ( empty( $nextRule ) ) {
				error_log( 'COULD NOT ORDER RULES!' );
				break;
			}

			$finalRulesOrder[] = $nextRule;

			unset( $rulesDependenciesMap[ $nextRule ] );
			foreach ( $rulesDependenciesMap as $ruleSlug => $deps ) {
				$remove = \array_search( $nextRule, $deps );
				if ( $remove !== false ) {
					unset( $rulesDependenciesMap[ $ruleSlug ][ $remove ] );
				}
			}

			if ( $count++ > 1000 ) {
				break;
			}
		} while ( !empty( $rulesDependenciesMap ) );

		$newRules = [];
		foreach ( $finalRulesOrder as $rule ) {
			$newRules[ $rule ] = $this->rules[ $rule ];
		}
		$this->rules = $newRules;
	}

	private function findRuleWithZeroDependencies( array $map ) {
		$theRule = '';
		foreach ( $map as $rule => $deps ) {
			if ( \count( $deps ) === 0 ) {
				$theRule = $rule;
				break;
			}
		}
		return $theRule;
	}

	/**
	 * Assigns the most appropriate WP Hook to a rule based on its conditions.
	 */
	private function assignWpHooks() {
		foreach ( $this->rules as $rule ) {

			$minimumHook = WPHooksOrder::NONE;
			foreach ( $this->dependencies[ $rule->slug ] as $dependency ) {
				if ( !$this->isRule( $dependency ) ) {
//					error_log( $dependency );
					// only conditions have WP hooks.
					try {
						/** @var Base $class */
						$class = self::con()->rules->locateConditionHandlerClass( $dependency );
//						error_log( $class );
						$minimumHook = \max( $minimumHook, $class::FindMinimumHook() );
//						error_log( var_export( $minimumHook, true ) );
					}
					catch ( NoSuchConditionHandlerException $e ) {
//						error_log( $e->getMessage() );
					}
				}
			}

			$rule->wp_hook_level = \max( $minimumHook, $rule->wp_hook_level );
			$rule->wp_hook = WPHooksOrder::HOOK_NAME( $rule->wp_hook_level );
		}
	}

	private function buildDependencyGraph() {
		foreach ( $this->rules as $rule ) {
			if ( !isset( $dependencies[ $rule->slug ] ) ) {
				$dependencies[ $rule->slug ] = [];
			}
			$this->dependencies[ $rule->slug ] = $this->buildRuleDependencies( $rule );
		}
	}

	private function isRule( string $ruleSlug ) :bool {
		return isset( $this->rules[ $ruleSlug ] );
	}

	private function buildRuleDependencies( RuleVO $rule ) :array {
		$deps = [];

		foreach ( $this->getAllConditionsProperty( $rule->conditions[ 'group' ], 'condition' ) as $conditionSlug ) {

			// First we add the condition to the list of dependencies.
			$deps[] = $conditionSlug;

			// Then we get the list of dependencies for this condition.
			if ( !isset( $this->dependencies[ $conditionSlug ] ) ) {
				try {
					/** @var Base $handlerClass */
					$handlerClass = self::con()->rules->locateConditionHandlerClass( $conditionSlug );
					$this->dependencies[ $conditionSlug ] = \array_map(
						function ( $className ) {
							/** Base */
							return $className::SLUG;
						},
						$handlerClass::BuildRequiredConditions()
					);
				}
				catch ( NoSuchConditionHandlerException $e ) {
					error_log( 'NO SUCH: '.$conditionSlug );
					continue;
				}
			}

			$deps = \array_merge( $deps, $this->dependencies[ $conditionSlug ] );
		}

		foreach ( $this->getAllConditionsProperty( $rule->conditions[ 'group' ], 'rule' ) as $ruleSlug ) {
			if ( $this->isRule( $ruleSlug ) ) {
				$deps[] = $ruleSlug;
				$deps = \array_merge( $deps, $this->buildRuleDependencies( $this->rules[ $ruleSlug ] ) );
			}
		}

		return \array_unique( $deps );
	}

	/**
	 * This is recursive and essentially allows for infinite nesting of groups of rules with different logic.
	 */
	private function getAllConditionsProperty( array $conditions, string $subPropertyToCollect = 'condition' ) :array {
		$collection = [];

		foreach ( $conditions as $subCondition ) {
			if ( isset( $subCondition[ 'group' ] ) ) {
				$collection = \array_merge( $collection, $this->getAllConditionsProperty( $subCondition[ 'group' ] ) );
			}
			elseif ( !empty( $subCondition[ $subPropertyToCollect ] ) ) {
				$collection[] = $subCondition[ $subPropertyToCollect ];
			}
		}

		return \array_unique( $collection );
	}
}