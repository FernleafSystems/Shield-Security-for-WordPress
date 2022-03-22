<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string[] $match_ips
 */
abstract class Base extends DynPropertiesClass {

	use PluginControllerConsumer;

	const SLUG = 'is_fake_web_crawler';

	protected $conditionTriggerMeta = [];

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
					$value = Services::IP()->getRequestIp();
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
			return $this->execConditionCheck();
		}
		catch ( \Exception $e ) {
			return false;
		}
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