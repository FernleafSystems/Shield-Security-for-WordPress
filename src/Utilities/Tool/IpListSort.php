<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

class IpListSort {

	/**
	 * @param string[] $IPs
	 * @return array
	 */
	public static function Sort( $IPs ) :array {
		if ( \is_array( $IPs ) ) {
			$ip4 = \array_filter( $IPs, function ( $sIP ) {
				return \strpos( $sIP, '.' ) > 0;
			} );
			$ip6 = \array_filter( $IPs, function ( $sIP ) {
				return \strpos( $sIP, ':' ) > 0;
			} );
			\asort( $ip4 );
			\asort( $ip6 );
			$IPs = \array_merge( $ip4, $ip6 );
		}
		return \is_array( $IPs ) ? $IPs : [];
	}
}