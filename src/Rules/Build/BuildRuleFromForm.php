<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\RequestBypassesAllRestrictions;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\FindFromSlug;

class BuildRuleFromForm extends BuildRuleBase {

	use PluginControllerConsumer;

	/**
	 * @var RuleFormBuilderVO
	 */
	private $form;

	public function __construct( RuleFormBuilderVO $form ) {
		$this->form = $form;
	}

	protected function getName() :string {
		return $this->form->name;
	}

	/**
	 * TODO: Allow users to set response timings.
	 */
	protected function isInstantExecResponse() :bool {
		return true;
	}

	protected function getDescription() :string {
		return $this->form->description;
	}

	protected function getConditions() :array {
		return $this->parseConditions( $this->form->getRawData() );
	}

	/**
	 * There's a bit of hard-coding of the logic here as we don't have multi-level logic yet. So we just assume a single
	 * level with no sub-conditions.  Not ideal, but we'll add depth in the future and this will need to be updated.
	 * @see parseConditionsRecursive()
	 */
	private function parseConditions( array $conditionsToParse ) :array {
		$conditions = [
			'logic'      => $conditionsToParse[ 'conditions_logic' ],
			'conditions' => [],
		];
		foreach ( $conditionsToParse[ 'conditions' ] as $singleCondition ) {
			$subCondition = [
				'logic'      => $singleCondition[ 'invert' ][ 'value' ],
				'conditions' => FindFromSlug::Condition( $singleCondition[ 'value' ] ),
				'params'     => [],
			];
			foreach ( $singleCondition[ 'params' ] ?? [] as $paramValueDef ) {
				$value = $paramValueDef[ 'value' ];
				if ( ( $paramValueDef[ 'param_subtype' ] ?? null ) === EnumParameters::SUBTYPE_REGEX ) {
					$value = \addslashes( $value );
				}
				elseif ( $paramValueDef[ 'param_type' ] === EnumParameters::TYPE_BOOL ) {
					$value = $paramValueDef[ 'value' ] === 'Y';
				}
				// subtype is set as the form builder processes submitted form. We don't store with added slashes.

				$subCondition[ 'params' ][ $paramValueDef[ 'name' ] ] = $value;
			}
			$conditions[ 'conditions' ][] = $subCondition;
		}

		/**
		 * We automatically add Invert-RequestBypassesAllRestrictions if the checkbox to do so is provided.
		 */
		if ( $this->form->checks[ 'checkbox_auto_include_bypass' ][ 'value' ] === 'Y' ) {
			$containsBypassCondition = false;
			foreach ( $conditions[ 'conditions' ] as $condition ) {
				if ( $condition[ 'conditions' ] === RequestBypassesAllRestrictions::class
					 && $condition[ 'logic' ] === EnumLogic::LOGIC_AND ) {
					$containsBypassCondition = true;
					break;
				}
			}

			if ( $conditions[ 'logic' ] === EnumLogic::LOGIC_OR ) {
				$conditions = [
					'logic'      => EnumLogic::LOGIC_AND,
					'conditions' => [
						[
							'logic'      => EnumLogic::LOGIC_INVERT,
							'conditions' => RequestBypassesAllRestrictions::class,
						],
						$conditions
					],
				];
			}
			elseif ( !$containsBypassCondition ) {
				\array_unshift( $conditions[ 'conditions' ], [
					'logic'      => EnumLogic::LOGIC_INVERT,
					'conditions' => RequestBypassesAllRestrictions::class,
				] );
			}
		}

		// Small optimisation to flatten conditions if there's only 1.
		if ( \count( $conditions[ 'conditions' ] ) === 1 ) {
			$conditions = \array_pop( $conditions[ 'conditions' ] );
		}

		return $conditions;
	}

	private function parseConditionsRecursive( array $conditionsToParse ) :array {

		if ( \count( $conditionsToParse[ 'conditions' ] ) === 1 ) {
			$singleCondition = \array_pop( $conditionsToParse[ 'conditions' ] );
			$conditions = [
				'logic'      => $singleCondition[ 'invert' ][ 'value' ],
				'conditions' => FindFromSlug::Condition( $singleCondition[ 'value' ] ),
				'params'     => [],
			];
			foreach ( $singleCondition[ 'params' ] as $paramValueDef ) {
				$singleCondition[ 'params' ][ $paramValueDef[ 'name' ] ] = $paramValueDef[ 'value' ];
			}
		}
		else {
			$conditions = [
				'logic'      => $conditionsToParse[ 'conditions_logic' ],
				'conditions' => [],
			];
			foreach ( $conditionsToParse[ 'conditions' ] as $conditionToParse ) {
				$conditions[ 'conditions' ][] = $this->parseConditions( $conditionToParse );
			}
		}
		return $conditions;
	}

	protected function getResponses() :array {
		return $this->parseResponses( $this->form->getRawData() );
	}

	private function parseResponses( array $responsesToParse ) :array {
		$responses = [];
		foreach ( $responsesToParse[ 'responses' ] as $responseToParse ) {
			$response = [
				'response' => FindFromSlug::Response( $responseToParse[ 'value' ] ),
				'params'   => [],
			];
			foreach ( $responseToParse[ 'params' ] ?? [] as $paramDef ) {
				$value = $paramDef[ 'value' ];
				if ( $paramDef[ 'param_type' ] === EnumParameters::TYPE_BOOL ) {
					$value = $paramDef[ 'value' ] === 'Y';
				}
				$response[ 'params' ][ $paramDef[ 'name' ] ] = $value;
			}
			$responses[] = $response;
		}
		return $responses;
	}

	protected function getSlug() :string {
		return 'custom/'.sanitize_key( $this->form->name );
	}
}