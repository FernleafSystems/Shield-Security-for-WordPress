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
		$this->extractConditionsAndResponses();
		$this->extractedForm->conditions_logic = $this->extractConditionsLogic();
		$this->counts();
		$this->extractedForm->has_errors = $this->hasErrors;

		$this->nameAndDescription();
		$this->handleChecks();
		$this->assessReadiness();

		return $this->extractedForm;
	}

	private function nameAndDescription() :void {
		$this->extractedForm->name = \trim( \preg_replace( '#[^a-z0-9_\s-]#i', '', $this->form[ 'rule_name' ] ?? '' ) );
		$this->extractedForm->description = \trim( \preg_replace( '#[^a-z0-9_\s-]#i', '', $this->form[ 'rule_description' ] ?? '' ) );
		$this->extractedForm->edit_rule_id = $this->form[ 'edit_rule_id' ] ?? -1;
	}

	private function assessReadiness() :void {
		$ready = self::con()->caps->canCustomSecurityRules()
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

	private function handleChecks() :void {
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

	private function extractConditionsAndResponses() :void {

		$conditions = [];
		$responses = [];
		$unselectedConditionPresent = false;

		if ( !empty( $this->form ) ) {
			$allConditionIDs = [];
			$allResponseIDs = [];
			$availableConditions = GetAvailable::Conditions();
			$availableResponses = GetAvailable::Responses();
			foreach ( $this->form as $name => $value ) {
				if ( \preg_match( '#^(condition|response)_(\d+)$#', $name, $matches ) ) {

					$type = $matches[ 1 ];
					$isCondition = $type === 'condition';

					if ( $value === '--' ) {
						if ( $isCondition ) {
							$unselectedConditionPresent = true;
						}
						continue;
					}

					$id = (int)$matches[ 2 ];
					$isCondition ? $allConditionIDs[] = $id : $allResponseIDs[] = $id;

					$itemParams = [];
					$rawFormParams = $this->extractParamsForItem( $name );
					$paramsDefForItem = $this->findDefFromSlug( $value, $isCondition ? $availableConditions : $availableResponses )[ 'params_def' ];
					foreach ( $paramsDefForItem as $paramName => $paramDef ) {
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

						$itemParams[ $paramName ] = [
							'type'        => $isCondition ? 'condition_param' : 'response_param',
							'name'        => $paramName,
							'value'       => $paramValue,
							'param_type'  => $paramDef[ 'type' ],
							'enum_labels' => $paramDef[ 'enum_labels' ] ??
											 \array_intersect_key( EnumMatchTypes::MatchTypeNames(), \array_flip( $paramDef[ 'type_enum' ] ?? [] ) ),
							'label'       => $paramDef[ 'label' ],
							'error'       => $error,
						];
					}

					/**
					 * After verifying all the parameters, we want to go back and look at any parameters
					 * that have a match_type set to Regex, and see if the value to match against is in-fact a valid
					 * regular expression.
					 */
					foreach ( $itemParams as $paramName => $paramDetails ) {
						$forParamName = $paramsDefForItem[ $paramName ][ 'for_param' ] ?? null;
						if ( !empty( $forParamName ) && $paramDetails[ 'value' ] === EnumMatchTypes::MATCH_TYPE_REGEX ) {
							$forParamDetails = $itemParams[ $forParamName ];
							$forParamDetails[ 'param_subtype' ] = EnumParameters::SUBTYPE_REGEX;
							if ( empty( $forParamDetails[ 'error' ] ) && @\preg_match( \addslashes( $forParamDetails[ 'value' ] ), '' ) === false ) {
								$forParamDetails[ 'error' ] = __( 'Please provide a valid regular expression.', 'wp-simple-firewall' );
								$this->hasErrors = true;
							}
							$itemParams[ $forParamName ] = $forParamDetails;
						}
					}

					if ( $isCondition ) {
						$conditions[ $name ] = [
							'type'   => 'condition',
							'name'   => $name,
							'value'  => $value,
							'params' => $itemParams,
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
					else {
						$responses[ $name ] = [
							'type'   => 'response',
							'name'   => $name,
							'value'  => $value,
							'params' => $itemParams,
						];
					}
				}
			}
		}

		{ // Post-process Conditions
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

			$this->extractedForm->conditions = $conditions;
		}

		{ // Post-Process Responses
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
			$this->extractedForm->responses = $responses;
		}
	}

	private function extractParamsForItem( string $itemName ) :array {
		$params = [];
		foreach ( $this->form as $name => $value ) {
			if ( \preg_match( sprintf( '#^%s_param_(.+)$#', $itemName ), $name, $matches ) ) {
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
				$newHash = \hash( 'sha1', \serialize( $item ) );

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