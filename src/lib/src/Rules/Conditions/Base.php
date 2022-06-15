<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;
use FernleafSystems\Wordpress\Services\Services;

abstract class Base extends DynPropertiesClass {

	use PluginControllerConsumer;

	const SLUG = '';

	protected $conditionTriggerMeta = [];

	/**
	 * @var RuleVO
	 */
	protected $rule;

	public function __construct( array $conditionParams = [] ) {
		$this->applyFromArray( $conditionParams );
	}

	public function setRule( RuleVO $rule ) :self {
		$this->rule = $rule;
		return $this;
	}

	public static function BuildRequiredConditions() :array {
		$conditions = static::RequiredConditions();
		foreach ( static::RequiredConditions() as $requiredCondition ) {
			/** @var $requiredCondition Base */
			$conditions = array_merge( $conditions, $requiredCondition::BuildRequiredConditions() );
		}
		return array_unique( $conditions );
	}

	public static function FindMinimumHook() :int {
		$minimum = static::MinimumHook();
		foreach ( static::BuildRequiredConditions() as $requiredCondition ) {
			/** @var $requiredCondition Base */
			$minimum = max( $minimum, $requiredCondition::MinimumHook() );
		}
		return (int)$minimum;
	}

	public static function RequiredConditions() :array {
		return [];
	}

	public static function MinimumHook() :int {
		return WPHooksOrder::NONE;
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'match_ips':
			case 'match_ip_ids':
			case 'match_not_ip_ids':
			case 'match_useragents':
				if ( !is_array( $value ) ) {
					$value = [];
				}
				break;
			case 'request_ip':
				if ( empty( $value ) ) {
					$value = $this->getCon()->this_req->ip;
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
		try {
			$result = $this->execConditionCheck();
		}
		catch ( \Exception $e ) {
			$result = false;
		}
		return $result;
	}

	public function getConditionTriggerMetaData() :array {
		return $this->conditionTriggerMeta;
	}

	/**
	 * @throws \Exception
	 */
	abstract protected function execConditionCheck() :bool;

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
}