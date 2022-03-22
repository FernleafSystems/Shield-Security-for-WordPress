<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $ip
 * @property bool   $is_trusted_bot
 * @property bool   $rules_completed
 */
class ThisRequest extends DynPropertiesClass {

	use Shield\Modules\PluginControllerConsumer;

	public function __construct( Shield\Controller\Controller $con ) {
		$this->setCon( $con );
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {

			case 'ip':
				$value = Services::IP()->getRequestIp();
				break;

			case 'is_trusted_bot':
			case 'rules_completed':
				$value = (bool)$value;
				break;

			default:
				break;
		}
		return $value;
	}

	private function init() {


	}
}