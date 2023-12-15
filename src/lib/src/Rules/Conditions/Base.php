<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\{
	ConditionsVO,
	Enum,
	RuleVO,
	Traits\AutoSnakeCaseSlug,
	WPHooksOrder
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Processors\ProcessConditions;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Strings;

abstract class Base extends DynPropertiesClass {

	use AutoSnakeCaseSlug;
	use PluginControllerConsumer;

	public const SLUG = '';

	protected $conditionTriggerMeta = [];

	/**
	 * @var RuleVO
	 */
	protected $rule;

	/**
	 * @var array
	 */
	protected $params;

	public function __construct( array $params = [] ) {
		$this->setParams( $params );
	}

	protected function setParams( array $params ) {
		foreach ( $this->getParamsDef() as $key => $def ) {
			if ( !isset( $params[ $key ] ) ) {
				$default = $def[ 'default' ] ?? null;
				if ( $default === null ) {
					switch ( $def[ 'type' ] ) {
						case Enum\EnumParameters::TYPE_ARRAY:
							$default = [];
							break;
						case Enum\EnumParameters::TYPE_STRING:
						default:
							$default = '';
							break;
					}
				}
				$params[ $key ] = $default;
			}
		}
		$this->applyFromArray( $params );
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
			sprintf( '#\b(%s)\b#i', \implode( '|', [ 'wp', 'ip', 'ajax', 'wpcli', 'ade' ] ) ),
			function ( $matches ) {
				return \strtoupper( $matches[ 0 ] );
			},
			\ucwords( \str_replace( '_', ' ', $this->getSlug() ) )
		);
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'match_ips':
			case 'match_ip_ids':
			case 'match_useragents':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;
			case 'request_ip':
				if ( empty( $value ) ) {
					$value = self::con()->this_req->ip;
				}
				break;
			case 'request_useragent':
				if ( empty( $value ) ) {
					$value = Services::Request()->getUserAgent();
				}
				break;
			default:
				break;
		}
		return $value;
	}

	public function run() :bool {
		$result = $this->getPreviousResult();
		if ( $result === null ) {
			try {
				$result = ( new ProcessConditions( $this->getSubConditionsVO() ) )->process();
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

	/**
	 * @return mixed|null
	 */
	protected function getConditionTriggerMeta( string $item ) {
		return $this->conditionTriggerMeta[ $item ] ?? null;
	}

	protected function removeTriggerMeta( string $item ) :self {
		unset( $this->conditionTriggerMeta[ $item ] );
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
	 * @deprecated 18.5.8
	 */
	public static function RequiredConditions() :array {
		return [];
	}

	/**
	 * @deprecated 18.5.8
	 */
	public static function FindMinimumHook() :int {
		return static::MinimumHook();
	}

	/**
	 * @deprecated 18.5.8
	 */
	public function setRule( RuleVO $rule ) :self {
		$this->rule = $rule;
		return $this;
	}
}