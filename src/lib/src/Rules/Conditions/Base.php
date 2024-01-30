<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	Common,
	ConditionsVO,
	Enum,
	Processors,
	WPHooksOrder
};

abstract class Base extends Common\BaseConditionResponse {

	public const SLUG = '';

	public static function MinimumHook() :int {
		return WPHooksOrder::NONE;
	}

	public function run() :bool {
		$result = $this->getPreviousResult();
		if ( $result === null ) {
			try {
				$result = ( new Processors\ProcessConditions( $this->getSubConditionsVO() ) )
					->setThisRequest( $this->req )
					->process();
			}
			catch ( \Exception $e ) {
				$result = false;
			}
		}

		$this->postExecConditionCheck( $result );

		return $result;
	}

	protected function getPreviousResult() :?bool {
		return null;
	}

	/**
	 * @throws \Exception
	 */
	protected function execConditionCheck() :bool {
		return true;
	}

	protected function postExecConditionCheck( bool $result ) :void {
	}

	protected function addConditionTriggerMeta( string $item, $value ) :self {
		self::con()->rules->getConditionMeta()->{$item} = $value;
		return $this;
	}

	public function getSubConditionsVO() :ConditionsVO {
		return ( new ConditionsVO() )->applyFromArray( $this->getSubConditions() );
	}

	protected function getSubConditions() :array {
		return [
			'conditions' => $this->getDefaultConditionCheckCallable(),
		];
	}

	protected function getDefaultConditionCheckCallable() :\Closure {
		return function () {
			return $this->execConditionCheck();
		};
	}

	public function getType() :string {
		return Enum\EnumConditions::CONDITION_TYPE_NORMAL;
	}
}