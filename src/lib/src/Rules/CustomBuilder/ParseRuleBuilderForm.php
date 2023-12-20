<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumMatchTypes;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
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
		$this->deleteElements();
		$this->extractedForm->conditions = $this->extractConditions();
		$this->extractedForm->responses = $this->extractResponses();
		$this->extractedForm->conditions_logic = $this->extractConditionsLogic();
		$this->counts();
		$this->extractedForm->has_errors = $this->hasErrors;

		$this->nameAndDescription();
		$this->handleCheckboxes();
		$this->assessReadiness();

		return $this->extractedForm;
	}

	private function nameAndDescription() :void {
		$this->extractedForm->name = \trim( \preg_replace( '#[^a-z0-9_\s-]#i', '', $this->form[ 'rule_name' ] ?? '' ) );
		$this->extractedForm->description = \trim( \preg_replace( '#[^a-z0-9_\s-]#i', '', $this->form[ 'rule_description' ] ?? '' ) );
		$this->extractedForm->edit_rule_id = $this->form[ 'edit_rule_id' ] ?? -1;
	}

	private function assessReadiness() :void {
		$ready = self::con()->caps->canCreateCustomRules()
				 && !$this->hasErrors
				 && !empty( $this->extractedForm->name )
				 && !empty( $this->extractedForm->description )
				 && $this->extractedForm->count_set_conditions > 0
				 && $this->extractedForm->count_set_responses > 0;
		if ( $ready ) {
			foreach ( $this->extractedForm->checks as $check ) {
				if ( $check[ 'value' ] !== 'Y' ) {
					$ready = false;
					break;
				}
			}
		}
		$this->extractedForm->ready_to_create = $ready;
	}

	private function handleCheckboxes() :void {
		$autoInclude = $this->form[ 'checkbox_auto_include_bypass' ] ?? 'Y';
		$checks = [
			'checkbox_auto_include_bypass' => [
				'name'  => 'checkbox_auto_include_bypass',
				'value' => $autoInclude,
				'label' => __( "Automatically honour Shield's existing whitelisting rules and exceptions.", 'wp-simple-firewall' ),
			],
		];

		if ( $autoInclude !== 'Y' ) {
			$checks[ 'checkbox_has_bypass_all_inverted' ] = [
				'name'  => 'checkbox_has_bypass_all_inverted',
				'value' => $this->form[ 'checkbox_has_bypass_all_inverted' ] ?? 'N',
				'label' => __( "I understand the risks of creating a rule that doesn't honour Shield's whitelists and exceptions, and I may find it difficult to regain access if I get locked out.", 'wp-simple-firewall' ),
			];
		}

		$checks[ 'checkbox_accept_rules_warning' ] = [
			'name'  => 'checkbox_accept_rules_warning',
			'value' => $this->form[ 'checkbox_accept_rules_warning' ] ?? 'N',
			'label' => __( "Creating custom rules is an advanced feature and I accept full responsibility for any problems arising from the rules I create.", 'wp-simple-firewall' ),
		];

		$this->extractedForm->checks = $checks;
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
		return $this->form[ 'conditions_logic' ] ?? EnumLogic::LOGIC_AND;
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
					foreach ( $this->findDefFromSlug( $value, GetAvailable::Conditions() )[ 'params_def' ] as $paramName => $paramDef ) {

						$paramValue = $rawFormParams[ $paramName ] ?? null;
						try {
							$paramValue = ( new VerifyParams() )->verifyParam( $paramValue, $paramDef, $paramName );
							if ( $paramDef[ 'type' ] === EnumParameters::TYPE_BOOL ) {
								$paramValue = $paramValue ? 'Y' : 'N';
							}
							$error = '';
						}
						catch ( \Exception $e ) {
							$error = $e->getMessage();
							$this->hasErrors = true;
						}

						$conditionParams[ $paramName ] = [
							'type'        => 'condition_param',
							'name'        => $paramName,
							'value'       => $paramValue,
							'param_type'  => $paramDef[ 'type' ],
							'enum_labels' => $paramDef[ 'enum_labels' ] ??
											 \array_intersect_key( EnumMatchTypes::MatchTypeNames(), \array_flip( $paramDef[ 'type_enum' ] ?? [] ) ),
							'label'       => $paramDef[ 'label' ],
							'error'       => $error,
						];
					}

					$conditions[ $name ] = [
						'type'   => 'condition',
						'name'   => $name,
						'value'  => $value,
						'params' => $conditionParams,
						'invert' => [
							'name'    => 'invert',
							'value'   => $this->form[ $name.'_invert' ] ?? EnumLogic::LOGIC_ASIS,
							'options' => [
								EnumLogic::LOGIC_ASIS   => 'As-Is',
								EnumLogic::LOGIC_INVERT => 'Invert',
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
					'value'   => EnumLogic::LOGIC_ASIS,
					'options' => [
						EnumLogic::LOGIC_ASIS   => 'As-Is',
						EnumLogic::LOGIC_INVERT => 'Invert',
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
							'type'        => 'response_param',
							'name'        => $paramName,
							'value'       => $paramValue,
							'param_type'  => $paramDef[ 'type' ],
							'enum_labels' => $paramDef[ 'enum_labels' ] ??
											 \array_intersect_key( EnumMatchTypes::MatchTypeNames(), \array_flip( $paramDef[ 'type_enum' ] ?? [] ) ),
							'label'       => $paramDef[ 'label' ],
							'error'       => $error,
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