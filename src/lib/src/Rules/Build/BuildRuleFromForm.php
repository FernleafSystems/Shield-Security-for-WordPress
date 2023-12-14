<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\FindFromSlug;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\PasswordGenerator;

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
		return PasswordGenerator::Gen( 6, false, true, false );
	}

	protected function getDescription() :string {
		return 'Random desc'.wp_rand();
	}

	protected function getConditions() :array {
		return $this->convertFormConditionToRuleCondition( $this->form->conditions );
	}

	private function convertFormConditionToRuleCondition( array $formCondition ) :array {
		if ( \count( $formCondition ) === 1 ) {
			$params = [];
			foreach ( $formCondition[ 'params' ] as $paramName => $paramValue ) {
				$params[ $paramName ] = $paramValue[ 'value' ];
			}
			$ruleCondition = [
				'conditions' => FindFromSlug::Condition( $formCondition[ 'value' ] ),
				'logic'      => $formCondition[ 'invert' ][ 'value' ],
				'params'     => $params,
			];
		}
		else {
			$ruleCondition = [
				'conditions' => \array_map(
					function ( array $innerFormCondition ) {
						return $this->convertFormConditionToRuleCondition( $innerFormCondition );
					},
					$formCondition
				),
				'logic'      => $this->form->conditions_logic,
			];
		}

		return $ruleCondition;
	}

	protected function getResponses() :array {
		$final = [];
		foreach ( $this->form->responses as $response ) {
			$params = [];
			foreach ( $response[ 'params' ] as $paramName => $paramValue ) {
				$params[ $paramName ] = $paramValue[ 'value' ];
			}
			$final[] = [
				'response' => FindFromSlug::Response( $response[ 'value' ] ),
				'params'   => $params,
			];
		}
		return $final;
	}

	protected function getSlug() :string {
		return 'test'.wp_rand();
	}
}