<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpMeta\LoadIpMeta;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class LookupMeta {

	use PluginControllerConsumer;
	use IpAddressConsumer;

	private static $IPs = [];

	public function countryCode() :string {
		$ip = $this->getIP();
		if ( !isset( self::$IPs[ $ip ] ) ) {
			if ( $ip === self::con()->this_req->ip ) {
				self::$IPs[ $ip ] = self::con()->this_req->ip_meta_record;
			}
			else {
				self::$IPs[ $ip ] = ( new LoadIpMeta() )->single( $ip );
			}
		}
		return empty( self::$IPs[ $ip ] ) ? '' : self::$IPs[ $ip ]->country_iso2;
	}
}