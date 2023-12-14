<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\VerifyParams;

class ParseRuleBuilderForm {

	use PluginControllerConsumer;

	private $form;

	private $action;

	private $actionVars;

	/**
	 * @var RuleFormBuilderVO
	 */
	private $extractedForm;

	private $hasErrors = false;

	public function __construct( array $form, string $action = '', ?array $actionVars = [] ) {
		$this->form = $form;
		$this->action = $action;
		$this->actionVars = $actionVars;
		$this->extractedForm = new RuleFormBuilderVO();
	}

	public function parseForm() :RuleFormBuilderVO {
		if ( $this->action === 'reset' ) {
			$this->form = [];
		}

		$this->deleteElements();
		$this->extractedForm->conditions = $this->extractConditions();
		$this->extractedForm->responses = $this->extractResponses();
		$this->extractedForm->conditions_logic = $this->extractConditionsLogic();
		$this->counts();
		$this->extractedForm->has_errors = $this->hasErrors;

		$this->nameAndDescription();
		$this->assessReadiness();

		return $this->extractedForm;
	}

	private function nameAndDescription() :void {
		$this->extractedForm->rule_name = \trim( \preg_replace( '#[^a-z0-9_\s-]#i', '', $this->form[ 'rule_name' ] ?? '' ) );
		$this->extractedForm->rule_description = \trim( \preg_replace( '#[^a-z0-9_\s-]#i', '', $this->form[ 'rule_description' ] ?? '' ) );
	}

	private function assessReadiness() :void {
		$this->extractedForm->ready_to_create = !$this->hasErrors
												&& !empty( $this->extractedForm->rule_name )
												&& !empty( $this->extractedForm->rule_description )
												&& $this->extractedForm->count_set_conditions > 0
												&& $this->extractedForm->count_set_responses > 0;
	}

	private function deleteElements() :void {
		if ( $this->action === 'delete_condition' ) {
			unset( $this->form[ $this->actionVars[ 'condition_name' ] ] );
		}
		elseif ( $this->action === 'delete_response' ) {
			unset( $this->form[ $this->actionVars[ 'response_name' ] ] );
		}
	}

	private function counts() :void {
		if ( !isset( $this->extractedForm->count_set_conditions ) ) {
			$this->extractedForm->count_set_conditions = 0;
			foreach ( $this->extractedForm->conditions as $conditionsDatum ) {
				if ( $conditionsDatum[ 'type' ] === 'condition' && $conditionsDatum[ 'value' ] !== '--' ) {
					$this->extractedForm->count_set_conditions++;
				}
			}
		}
		if ( !isset( $this->extractedForm->count_set_responses ) ) {
			$this->extractedForm->count_set_responses = 0;
			foreach ( $this->extractedForm->responses as $responseDatum ) {
				if ( $responseDatum[ 'type' ] === 'response' && $responseDatum[ 'value' ] !== '--' ) {
					$this->extractedForm->count_set_responses++;
				}
			}
		}
	}

	private function extractConditionsLogic() :string {
		return $this->form[ 'conditions_logic' ] ?? Constants::LOGIC_AND;
	}

	private function extractConditions() :array {

		$conditions = [];
		$unselectedConditionPresent = false;

		if ( !empty( $this->form ) ) {
			$allConditionIDs = [];
			foreach ( $this->form as $name => $value ) {
				if ( \preg_match( '#^condition_(\d+)$#', $name, $matches ) ) {

					if ( $value === '--' ) {
						$unselectedConditionPresent = true;
						continue;
					}

					$allConditionIDs[] = (int)$matches[ 1 ];

					$conditionParams = [];
					$rawFormParams = $this->extractParamsForCondition( $name );
					$conditionDef = $this->findDefFromSlug( $value, GetAvailable::Conditions() );
					foreach ( $conditionDef[ 'params_def' ] as $paramName => $paramDef ) {

						$paramValue = $rawFormParams[ $paramName ] ?? null;
						try {
							$paramValue = ( new VerifyParams() )->verifyParam( $paramValue, $paramDef, $paramName );
							if ( $paramDef[ 'type' ] === 'bool' ) {
								$paramValue = $paramValue ? 'Y' : 'N';
							}
							$error = '';
						}
						catch ( \Exception $e ) {
							$error = $e->getMessage();
							$this->hasErrors = true;
						}

						$conditionParams[ $paramName ] = [
							'type'       => 'condition_param',
							'name'       => $paramName,
							'value'      => $paramValue,
							'param_type' => $paramDef[ 'type' ],
							'label'      => $paramDef[ 'label' ],
							'error' => $error,
						];
					}

					$conditions[ $name ] = [
						'type'   => 'condition',
						'name'   => $name,
						'value'  => $value,
						'params' => $conditionParams,
						'invert' => [
							'name'    => 'invert',
							'value'   => $this->form[ $name.'_invert' ] ?? Constants::LOGIC_ASIS,
							'options' => [
								Constants::LOGIC_ASIS   => 'As-Is',
								Constants::LOGIC_INVERT => 'Invert',
							],
						],
					];
				}
			}
		}

		$countPreDuplicates = \count( $conditions );
		$conditions = $this->removeDuplicates( $conditions );

		if ( $unselectedConditionPresent
			 || $countPreDuplicates > \count( $conditions )
			 || \count( $conditions ) === 0
			 || $this->action === 'add_condition' ) {

			$this->extractedForm->has_unset_condition = true;
			$nextID = empty( $allConditionIDs ) ? 1 : \max( $allConditionIDs ) + 1;
			$conditions[] = [
				'type'   => 'condition',
				'name'   => 'condition_'.$nextID,
				'value'  => '--',
				'invert' => [
					'name'    => 'condition_'.$nextID.'_invert',
					'value'   => Constants::LOGIC_ASIS,
					'options' => [
						Constants::LOGIC_ASIS   => 'As-Is',
						Constants::LOGIC_INVERT => 'Invert',
					],
				],
			];
		}
		else {
			$this->extractedForm->has_unset_condition = false;
		}

		return $conditions;
	}

	private function extractResponses() :array {
		if ( !isset( $this->extractedForm->conditions ) ) {
			$this->extractedForm->conditions = $this->extractConditions();
		}

		if ( $this->extractedForm->count_set_conditions === 0 ) {
			$responses = [];
		}
		else {
			$allResponseIDs = [];
			$responses = [];
			foreach ( $this->form as $name => $value ) {
				if ( \preg_match( '#^response_(\d+)$#', $name, $matches ) ) {

					if ( $value === '--' ) {
						continue;
					}

					$allResponseIDs[] = (int)$matches[ 1 ];

					$response = [
						'type'   => 'response',
						'name'   => $name,
						'value'  => $value,
						'params' => []
					];
					$rawFormParams = $this->extractParamsForResponse( $name );
					$responseDef = $this->findDefFromSlug( $value, GetAvailable::Responses() );
					foreach ( $responseDef[ 'params_def' ] as $paramName => $paramDef ) {

						$paramValue = $rawFormParams[ $paramName ] ?? null;
						try {
							$paramValue = ( new VerifyParams() )->verifyParam( $paramValue, $paramDef, $paramName );
							if ( $paramDef[ 'type' ] === 'bool' ) {
								$paramValue = $paramValue ? 'Y' : 'N';
							}
							$error = '';
						}
						catch ( \Exception $e ) {
							$error = $e->getMessage();
							$this->hasErrors = true;
						}

						$this->hasErrors = $this->hasErrors || $paramValue === null;

						$response[ 'params' ][ $paramName ] = [
							'type'       => 'response_param',
							'name'       => $paramName,
							'value'      => $paramValue,
							'param_type' => $paramDef[ 'type' ],
							'label'      => $paramDef[ 'label' ],
							'error' => $error,
						];
					}

					$responses[ $name ] = $response;
				}
			}

			$countPreDuplicates = \count( $responses );
			$responses = $this->removeDuplicates( $responses );

			// We have conditions, but no responses
			if ( \count( $responses ) === 0
				 || $countPreDuplicates > \count( $responses )
				 || $this->action === 'add_response' ) {

				$this->extractedForm->has_unset_response = true;
				$responses[] = [
					'type'  => 'response',
					'name'  => 'response_'.( empty( $allResponseIDs ) ? 1 : \max( $allResponseIDs ) + 1 ),
					'value' => '--',
				];
			}
			else {
				$this->extractedForm->has_unset_response = false;
			}
		}

		return $responses;
	}

	private function extractParamsForCondition( string $conditionName ) :array {
		$params = [];
		foreach ( $this->form as $name => $value ) {
			if ( \preg_match( sprintf( '#^%s_param_(.+)$#', $conditionName ), $name, $matches ) ) {
				$params[ $matches[ 1 ] ] = $value;
			}
		}
		return $params;
	}

	private function extractParamsForResponse( string $responseName ) :array {
		$params = [];
		foreach ( $this->form as $name => $value ) {
			if ( \preg_match( sprintf( '#^%s_param_(.+)$#', $responseName ), $name, $matches ) ) {
				$params[ $matches[ 1 ] ] = $value;
			}
		}
		return $params;
	}

	private function removeDuplicates( array $collection ) :array {
		$hashes = [];
		return \array_filter(
			$collection,
			function ( array $item ) use ( &$hashes ) {
				unset( $item[ 'name' ] );

				\ksort( $item );
				$newHash = \sha1( \serialize( $item ) );

				$keep = !\in_array( $newHash, $hashes );
				if ( $keep ) {
					$hashes[] = $newHash;
				}
				return $keep;
			}
		);
	}

	private function findDefFromSlug( string $slug, array $collection ) :?array {
		$found = null;
		foreach ( $collection as $item ) {
			if ( $item[ 'slug' ] === $slug ) {
				$found = $item;
				break;
			}
		}
		return $found;
	}
}