<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Rules;

class ExtractSubConditions {

	private static $ConditionDeps = [];

	private static $AllConditions = [];

	/**
	 * @return array{classes: Rules\Conditions\Base[]|string[], callables: callable[]}
	 * @throws \Exception
	 */
	public function fromRule( Rules\RuleVO $ruleVO ) :array {
		return $this->fromConditions( $ruleVO->conditions );
	}

	/**
	 * @return array{classes: Rules\Conditions\Base[]|string[], callables: callable[]}
	 * @throws \Exception
	 */
	public function fromConditions( Rules\ConditionsVO $conditionsVO ) :array {
		$classes = [];
		$callables = [];

		if ( $conditionsVO->is_group ) {
			foreach ( $conditionsVO->conditions as $condition ) {
				$subConditions = $this->fromConditions( $condition );
				$classes = \array_merge( $classes, $subConditions[ 'classes' ] );
				$callables = \array_merge( $classes, $subConditions[ 'callables' ] );
			}
		}
		elseif ( $conditionsVO->is_single ) {
			$conditionClass = $conditionsVO->conditions;

			$classes[] = $conditionClass;

			// Does the condition have any sub conditions? Let's build them.
			if ( !isset( self::$ConditionDeps[ $conditionClass ] ) ) {

				if ( \in_array( $conditionClass, self::$AllConditions ) ) {
					throw new \Exception( sprintf( 'Found condition which may have a mutual dependency: %s', $conditionClass ) );
				}

				if ( empty( $conditionClass ) || !\class_exists( $conditionClass ) ) {
					throw new \Exception( sprintf( 'A Condition class is referenced but does not exist: %s', $conditionClass ) );
				}

				self::$AllConditions[] = $conditionClass;

				/** @var Rules\Conditions\Base $condition */
				$condition = new $conditionClass();
				self::$ConditionDeps[ $conditionClass ] = $this->fromConditions( $condition->getSubConditionsVO() );
			}

			$classes = \array_merge( $classes, self::$ConditionDeps[ $conditionClass ][ 'classes' ] );
			$callables = \array_merge( $callables, self::$ConditionDeps[ $conditionClass ][ 'callables' ] );
		}
		elseif ( $conditionsVO->is_callable ) {
			$callables[] = $conditionsVO->conditions;
		}
		else {
			error_log( 'SHOULD NEVER GET HERE.' );
		}

		return [
			'classes'   => \array_unique( $classes ),
			'callables' => $callables,
		];
	}
}