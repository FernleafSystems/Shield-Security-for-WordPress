<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\BotSignal\BotSignalRecord;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string          $ip
 * @property bool            $ip_is_public
 * @property BotSignalRecord $botsignal_record
 * @property string          $ip_id
 * @property bool            $is_force_off
 * @property bool            $is_security_admin
 * @property bool            $is_trusted_bot
 * @property bool            $is_ip_blocked
 * @property bool            $is_ip_blocked_crowdsec
 * @property bool            $is_ip_blocked_shield
 * @property bool            $is_ip_blocked_shield_auto
 * @property bool            $is_ip_blocked_shield_manual
 * @property bool            $is_ip_blacklisted
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

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {

			case 'ip':
				if ( !is_string( $value ) ) {
					$value = Services::Request()->ip();
					$this->ip = $value;
				}
				break;

			case 'ip_id':
				if ( is_null( $value ) ) {
					$value = $this->getIpID();
					$this->ip_id = $value;
				}
				break;

			case 'is_force_off':
			case 'is_ip_blocked_shield_auto':
			case 'is_ip_blocked_shield_manual':
			case 'is_ip_blocked_crowdsec':
			case 'is_ip_whitelisted':
			case 'request_bypasses_all_restrictions':
			case 'is_security_admin':
			case 'is_trusted_bot':
			case 'ip_is_public':
			case 'wp_is_ajax':
			case 'wp_is_wpcli':
			case 'wp_is_xmlrpc':
				$value = (bool)$value;
				break;

			case 'is_ip_blocked':
				if ( is_null( $value ) ) {
					$value = $this->is_ip_blocked_shield || $this->is_ip_blocked_crowdsec;
				}
				break;

			case 'is_ip_blocked_shield':
				if ( is_null( $value ) ) {
					$value = $this->is_ip_blocked_shield_auto || $this->is_ip_blocked_shield_manual;
				}
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