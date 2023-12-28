<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\BotSignal\BotSignalRecord;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string          $ip
 * @property bool            $ip_is_public
 * @property string          $ip_id
 *
 * @property string          $path
 * @property string          $script_name
 * @property string          $useragent
 *
 * @property BotSignalRecord $botsignal_record
 *
 * @property bool            $is_force_off
 * @property bool            $is_security_admin
 * @property bool            $is_trusted_bot
 * @property bool            $is_ip_blocked
 * @property bool            $is_ip_blocked_crowdsec
 * @property bool            $is_ip_blocked_shield
 * @property bool            $is_ip_blocked_shield_auto
 * @property bool            $is_ip_blocked_shield_manual
 * @property bool            $is_ip_high_reputation
 * @property bool            $is_ip_blacklisted
 * @property bool            $is_ip_whitelisted
 * @property bool            $is_server_loopback
 * @property bool            $request_bypasses_all_restrictions
 * @property bool            $request_subject_to_shield_restrictions
 * @property bool            $is_site_lockdown_active
 * @property bool            $is_site_lockdown_blocked
 * @property bool            $wp_is_admin
 * @property bool            $wp_is_networkadmin
 * @property bool            $wp_is_ajax
 * @property bool            $wp_is_wpcli
 * @property bool            $wp_is_xmlrpc
 * @property bool            $wp_is_permalinks_enabled
 */
class ThisRequest extends DynPropertiesClass {

	private static $thisRequest;

	public static function Instance( array $params = [] ) :ThisRequest {
		return self::$thisRequest ?? self::$thisRequest = new ThisRequest( $params );
	}

	public function __construct( array $params = [] ) {
		$WP = Services::WpGeneral();
		$srvIP = Services::IP();
		$req = Services::Request();

		$this->ip = $req->ip();
		$this->ip_is_public = !empty( $this->ip ) && $srvIP->isValidIp_PublicRemote( $this->ip );
		$this->ip_id = $srvIP->getIpDetector()->getIPIdentity();

		$this->path = empty( $req->getPath() ) ? '/' : $req->getPath();
		$this->useragent = $req->getUserAgent();
		$possible = \array_values( \array_unique( \array_map( '\basename', \array_filter( [
			$req->server( 'SCRIPT_NAME' ),
			$req->server( 'SCRIPT_FILENAME' ),
			$req->server( 'PHP_SELF' )
		] ) ) ) );
		$this->script_name = empty( $possible ) ? '' : \current( $possible );

		$this->wp_is_admin = is_network_admin() || is_admin();
		$this->wp_is_networkadmin = is_network_admin();
		$this->wp_is_ajax = $WP->isAjax();
		$this->wp_is_wpcli = $WP->isWpCli();
		$this->wp_is_xmlrpc = $WP->isXmlrpc();
		$this->wp_is_permalinks_enabled = $WP->isPermalinksEnabled();

		$this->applyFromArray( \array_merge( $this->getRawData(), $params ) );
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {

			case 'is_ip_blocked':
				if ( \is_null( $value ) ) {
					$value = $this->is_ip_blocked_shield || $this->is_ip_blocked_crowdsec;
				}
				break;

			case 'is_ip_blocked_shield':
				if ( \is_null( $value ) ) {
					$value = $this->is_ip_blocked_shield_auto || $this->is_ip_blocked_shield_manual;
				}
				break;

			default:
				break;
		}
		return $value;
	}

	/**
	 * @deprecated 18.6
	 */
	private function getIpID() :string {
		return Services::IP()->getIpDetector()->getIPIdentity();
	}
}