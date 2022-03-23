<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $ip
 * @property string $ip_id
 * @property bool   $is_bypass_restrictions
 * @property bool   $is_trusted_bot
 * @property bool   $is_ip_whitelisted
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

			case 'is_bypass_restrictions':
				$value = $this->is_trusted_bot || $this->is_ip_whitelisted;
				break;

			case 'ip_id':
				if ( is_null( $value ) ) {
					$value = $this->getIpID();
					$this->ip_id = $value;
				}
				break;

			case 'is_trusted_bot':
			case 'is_ip_whitelisted':
			case 'rules_completed':
				$value = (bool)$value;
				break;

			default:
				break;
		}
		return $value;
	}

	private function getIpID() :string {
		return Services::IP()->getIpDetector()->getIPIdentity();
	}
}