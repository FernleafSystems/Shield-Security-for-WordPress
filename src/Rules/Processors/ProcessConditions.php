<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequestConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	ConditionMetaStore,
	Conditions,
	ConditionsVO,
	Enum\EnumLogic,
	Exceptions\NoSuchConditionHandlerException
};

class ProcessConditions {

	use PluginControllerConsumer;
	use ThisRequestConsumer;

	private static $ConditionsCache;

	/**
	 * @var ConditionsVO
	 */
	private $condition;

	/**
	 * @throws \Exception
	 */
	public function __construct( ConditionsVO $condition ) {
		if ( !\is_array( self::$ConditionsCache ) ) {
			self::$ConditionsCache = [];
		}

		if ( empty( $condition->conditions ) ) {
			throw new \Exception( 'Invalid conditions configuration.' );
		}

		$this->condition = $condition;
	}

	/**
	 * This is recursive and allows for infinite nesting of groups of rules with different logics.
	 */
	public function process() :bool {

		if ( $this->condition->is_callable ) {
			$conditionsMetFinal = \call_user_func( $this->condition->conditions );
		}
		elseif ( $this->condition->is_single ) {
			try {
				$conditionsMetFinal = $this->processSingleCondition( $this->condition );
			}
			catch ( NoSuchConditionHandlerException $e ) {
				$conditionsMetFinal = false;
			}
		}
		else {
			$groupResult = null;
			$groupLogic = $this->condition->logic;
			foreach ( $this->condition->conditions as $subCondition ) {

				try {
					$conditionProc = new ProcessConditions( $subCondition );
					$subConditionsResult = $conditionProc->setThisRequest( $this->req )->process();
				}
				catch ( \Exception $e ) {
					$subConditionsResult = false;
				}

				if ( $groupResult === null ) {
					$groupResult = $subConditionsResult;
				}
				elseif ( $groupLogic === EnumLogic::LOGIC_AND ) {
					$groupResult = $groupResult && $subConditionsResult;
				}
				else {
					$groupResult = $groupResult || $subConditionsResult;
				}

				// Short circuit processing
				if ( ( $groupLogic === EnumLogic::LOGIC_AND && !$groupResult ) || ( $groupLogic === EnumLogic::LOGIC_OR && $groupResult ) ) {
					break;
				}
			}

			$conditionsMetFinal = $groupResult;
		}

		return $conditionsMetFinal;
	}

	/**
	 * @throws NoSuchConditionHandlerException
	 */
	private function processSingleCondition( ConditionsVO $condition ) :bool {
		$handlerClass = $condition->conditions;
		if ( !\class_exists( $handlerClass ) ) {
			throw new NoSuchConditionHandlerException( 'No such Condition Handler Class for: '.$handlerClass );
		}
		$conditionHandler = new $handlerClass();
		$conditionHandler->setThisRequest( $this->req )
						 ->setParams( $condition->params );

		$hash = $this->hashHandler( $conditionHandler );
		if ( !isset( self::$ConditionsCache[ $hash ] ) ) {
			self::$ConditionsCache[ $hash ] = $this->runCapturingMeta( $conditionHandler );
		}

		// Apply the condition's meta to the current rule, including when the result came from the cache.
		$metaStore = self::con()->rules->getConditionMeta();
		foreach ( self::$ConditionsCache[ $hash ][ 'meta' ] as $key => $value ) {
			$metaStore->{$key} = $value;
		}

		$matched = self::$ConditionsCache[ $hash ][ 'matched' ];
		return $condition->logic === EnumLogic::LOGIC_INVERT ? !$matched : $matched;
	}

	/**
	 * Runs the handler against an empty meta store so the meta it records can be cached with its result.
	 * @param Conditions\Base $handler
	 */
	private function runCapturingMeta( $handler ) :array {
		$rules = self::con()->rules;
		$ruleMeta = $rules->getConditionMeta();
		$rules->setConditionMeta( new ConditionMetaStore() );
		try {
			return [
				'matched' => $handler->run(),
				'meta'    => $rules->getConditionMeta()->getRawData(),
			];
		}
		finally {
			$rules->setConditionMeta( $ruleMeta );
		}
	}

	/**
	 * @param Conditions\Base|mixed $handler
	 */
	private function hashHandler( $handler ) :string {
		$data = [
			$handler->getRawData(),
			$handler->p->getRawData()
		];
		foreach ( $data as &$datum ) {
			\ksort( $datum );
		}
		return \hash( 'sha1', \get_class( $handler ).\serialize( $data ) );
	}
}