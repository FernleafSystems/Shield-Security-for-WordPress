<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;
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
			foreach ( $singleCondition[ 'params' ] as $paramValueDef ) {
				$subCondition[ 'params' ][ $paramValueDef[ 'name' ] ] = $paramValueDef[ 'value' ];
			}
			$conditions[ 'conditions' ][] = $subCondition;
		}

		// Small optimisation to flat conditions if there's only 1.
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
			foreach ( $responseToParse[ 'params' ] as $paramDef ) {
				$response[ 'params' ][ $paramDef[ 'name' ] ] = $paramDef[ 'value' ];
			}
			$responses[] = $response;
		}
		return $responses;
	}

	protected function getSlug() :string {
		return 'test'.wp_rand();
	}
}