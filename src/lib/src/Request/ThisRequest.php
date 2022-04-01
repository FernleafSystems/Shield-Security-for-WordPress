<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\BotSignal\BotSignalRecord;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string          $ip
 * @property BotSignalRecord $botsignal_record
 * @property bool            $rules_completed
 * @property string          $ip_id
 * @property bool            $is_force_off
 * @property bool            $is_security_admin
 * @property bool            $is_trusted_bot
 * @property bool            $is_ip_blocked
 * @property bool            $is_ip_whitelisted
 * @property bool            $is_server_loopback
 * @property bool            $request_bypasses_all_restrictions
 * @property bool            $wp_is_admin
 * @property bool            $wp_is_networkadmin
 * @property bool            $wp_is_ajax
 * @property bool            $wp_is_wpcli
 * @property bool            $wp_is_xmlrpc
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
				if ( empty( $value ) ) {
					$value = Services::IP()->getRequestIp();
					$this->ip = $value;
				}
				break;

			case 'ip_id':
				if ( is_null( $value ) ) {
					$value = $this->getIpID();
					$this->ip_id = $value;
				}
				break;

			case 'is_ip_blocked':
			case 'is_ip_whitelisted':
			case 'request_bypasses_all_restrictions':
			case 'is_security_admin':
			case 'is_trusted_bot':
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