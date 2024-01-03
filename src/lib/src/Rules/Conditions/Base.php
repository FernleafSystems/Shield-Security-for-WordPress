<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	ConditionsVO,
	Enum,
	Processors,
	WPHooksOrder
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Traits\{
	AutoSnakeCaseSlug,
	ParamsConsumer,
	RuleConsumer,
};
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequestConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Strings;

abstract class Base extends DynPropertiesClass {

	use PluginControllerConsumer;
	use AutoSnakeCaseSlug;
	use ParamsConsumer;
	use RuleConsumer;
	use ThisRequestConsumer;

	public const SLUG = '';

	/**
	 * @var array
	 */
	protected $params;

	protected $conditionTriggerMeta = [];

	public function __construct( array $params = [] ) {
		$this->setParams( $params );
	}

	public static function MinimumHook() :int {
		return WPHooksOrder::NONE;
	}

	public static function Slug() :string {
		return Strings::CamelToSnake( ( new \ReflectionClass( static::class ) )->getShortName() );
	}

	public function getDescription() :string {
		return 'description';
	}

	public function getName() :string {
		return \preg_replace_callback(
			sprintf( '#\b(%s)\b#i', \implode( '|', [ 'wp', 'http', 'https', 'ip', 'ajax', 'wpcli', 'ade' ] ) ),
			function ( $matches ) {
				return \strtoupper( $matches[ 0 ] );
			},
			\ucwords( \str_replace( '_', ' ', $this->getSlug() ) )
		);
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

	public function getConditionTriggerMetaData() :array {
		return $this->conditionTriggerMeta;
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
		$this->conditionTriggerMeta[ $item ] = $value;
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

	public function getParamsDef() :array {
		return [];
	}

	public function getType() :string {
		return Enum\EnumConditions::CONDITION_TYPE_NORMAL;
	}

	/**
	 * @deprecated 18.5.8
	 */
	public static function BuildRequiredConditions() :array {
		$conditions = static::RequiredConditions();
		foreach ( static::RequiredConditions() as $requiredCondition ) {
			/** @var $requiredCondition Base */
			$conditions = \array_merge( $conditions, $requiredCondition::BuildRequiredConditions() );
		}
		return \array_unique( $conditions );
	}

	/**
	 * @deprecated 18.6
	 */
	public static function RequiredConditions() :array {
		return [];
	}

	/**
	 * @deprecated 18.6
	 */
	public static function FindMinimumHook() :int {
		return static::MinimumHook();
	}

	/**
	 * @return mixed|null
	 * @deprecated 18.6
	 */
	protected function getConditionTriggerMeta( string $item ) {
		return $this->conditionTriggerMeta[ $item ] ?? null;
	}

	/**
	 * @deprecated 18.6
	 */
	protected function removeTriggerMeta( string $item ) :self {
		unset( $this->conditionTriggerMeta[ $item ] );
		return $this;
	}
}