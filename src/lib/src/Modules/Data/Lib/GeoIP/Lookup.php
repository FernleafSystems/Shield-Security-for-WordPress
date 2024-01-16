<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IpMeta\LoadIpMeta;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Lookup {

	use PluginControllerConsumer;
	use IpAddressConsumer;

	private static $IPs = [];

	public function countryCode() :string {
		$ip = $this->getIP();
		if ( !isset( self::$IPs[ $ip ] ) ) {
			$ipMeta = ( new LoadIpMeta() )->single( $ip );
			self::$IPs[ $ip ] = empty( $ipMeta ) ? '' : $ipMeta;
		}
		return empty( self::$IPs[ $ip ] ) ? '' : self::$IPs[ $ip ]->country_iso2;
	}
}