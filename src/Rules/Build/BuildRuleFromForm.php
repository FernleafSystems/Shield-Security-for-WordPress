<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\HookTimings;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Build\Core\Firewall as FirewallRuleBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\{
	IsRequestStatus404,
	MatchRequestPath,
	RequestBypassesAllRestrictions
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\{
	EnumLogic,
	EnumParameters
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\{
	EventFire,
	FirewallBlock
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\{
	FindFromSlug,
	ResponseParamsNormalizer
};

class BuildRuleFromForm extends BuildRuleBase {

	use PluginControllerConsumer;
	private const FALLBACK_TEMPLATE_REDIRECT_AFTER_WORDPRESS_REDIRECTS = 1001;

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

	protected function getWpHookPriority() :?int {
		$rawData = $this->form->getRawData();
		$logic = $rawData[ 'conditions_logic' ] ?? '';
		if ( !\in_array( $logic, [ EnumLogic::LOGIC_AND, EnumLogic::LOGIC_OR ], true ) ) {
			return parent::getWpHookPriority();
		}

		$requiresFinal404 = false;
		foreach ( $rawData[ 'conditions' ] ?? [] as $condition ) {
			$conditionRequiresFinal404 = ( $condition[ 'value' ] ?? '' ) === IsRequestStatus404::Slug()
				&& ( $condition[ 'invert' ][ 'value' ] ?? '' ) === EnumLogic::LOGIC_ASIS;
			if ( $logic === EnumLogic::LOGIC_OR && !$conditionRequiresFinal404 ) {
				return parent::getWpHookPriority();
			}
			$requiresFinal404 = $requiresFinal404 || $conditionRequiresFinal404;
		}

		if ( $requiresFinal404 ) {
			$hookTiming = HookTimings::class.'::TEMPLATE_REDIRECT_AFTER_WORDPRESS_REDIRECTS';
			return ( \defined( $hookTiming )
				? \constant( $hookTiming )
				: self::FALLBACK_TEMPLATE_REDIRECT_AFTER_WORDPRESS_REDIRECTS ) + 1;
		}
		return parent::getWpHookPriority();
	}

	protected function getConditions() :array {
		return $this->parseConditions( $this->form->getRawData() );
	}

	/**
	 * There's a bit of hard-coding of the logic here as we don't have multi-level logic yet. So we just assume a single
	 * level with no sub-conditions.  Not ideal, but we'll add depth in the future and this will need to be updated.
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

	protected function getResponses() :array {
		return $this->parseResponses( $this->form->getRawData() );
	}

	private function parseResponses( array $responsesToParse ) :array {
		$responses = [];
		foreach ( $responsesToParse[ 'responses' ] as $responseToParse ) {
			$responseClass = FindFromSlug::Response( $responseToParse[ 'value' ] );
			$response = [
				'response' => $responseClass,
				'params'   => [],
			];
			foreach ( $responseToParse[ 'params' ] ?? [] as $paramDef ) {
				$value = $paramDef[ 'value' ];
				if ( $paramDef[ 'param_type' ] === EnumParameters::TYPE_BOOL ) {
					$value = $paramDef[ 'value' ] === 'Y';
				}
				$response[ 'params' ][ $paramDef[ 'name' ] ] = $value;
			}
			$response[ 'params' ] = ( new ResponseParamsNormalizer() )->normalize( $responseClass, $response[ 'params' ] );
			$responses[] = $response;
		}

		$hasFirewallBlock = false;
		$hasFirewallEvent = false;
		foreach ( $responses as $response ) {
			$hasFirewallBlock = $hasFirewallBlock || $response[ 'response' ] === FirewallBlock::class;
			$hasFirewallEvent = $hasFirewallEvent || (
				$response[ 'response' ] === EventFire::class
				&& ( $response[ 'params' ][ 'event' ] ?? '' ) === 'firewall_block'
			);
		}
		if ( $hasFirewallBlock && !$hasFirewallEvent ) {
			foreach ( $responses as $position => $response ) {
				if ( $response[ 'response' ] === FirewallBlock::class ) {
					\array_splice( $responses, $position, 0, [ $this->buildFirewallEventResponse() ] );
					break;
				}
			}
		}
		return $responses;
	}

	private function buildFirewallEventResponse() :array {
		$auditParams = [
			'name'  => $this->form->name,
			'term'  => $this->form->description ?: $this->form->name,
			'param' => 'path',
			'scan'  => 'custom_rule',
			'type'  => 'custom',
		];
		$auditParamsMap = [];
		foreach ( $this->form->getRawData()[ 'conditions' ] ?? [] as $condition ) {
			if ( ( $condition[ 'value' ] ?? '' ) !== MatchRequestPath::SLUG ) {
				continue;
			}
			$params = [];
			foreach ( $condition[ 'params' ] ?? [] as $param ) {
				$params[ $param[ 'name' ] ] = $param[ 'value' ];
			}
			$auditParams[ 'term' ] = (string)( $params[ 'match_path' ] ?? '' ) ?: $auditParams[ 'term' ];
			$auditParams[ 'type' ] = (string)( $params[ 'match_type' ] ?? '' ) ?: 'custom';
			$auditParamsMap[ 'value' ] = 'path';
			break;
		}

		return FirewallRuleBuilder::eventResponseDefinition( $auditParamsMap, $auditParams );
	}

	protected function getSlug() :string {
		return 'custom/'.sanitize_key( $this->form->name );
	}
}
