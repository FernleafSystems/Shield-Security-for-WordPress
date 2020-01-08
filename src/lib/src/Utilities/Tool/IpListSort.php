<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool;

/**
 * Class IpListSort
 * @package FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool
 */
class IpListSort {

	/**
	 * @param string[] $aIPs
	 * @return array
	 */
	public static function Sort( $aIPs ) {
		if ( is_array( $aIPs ) ) {
			$aIp4 = array_filter( $aIPs, function ( $sIP ) {
				return strpos( $sIP, '.' ) > 0;
			} );
			$aIp6 = array_filter( $aIPs, function ( $sIP ) {
				return strpos( $sIP, ':' ) > 0;
			} );
			asort( $aIp4 );
			asort( $aIp6 );
			$aIPs = array_merge( $aIp4, $aIp6 );
		}
		return is_array( $aIPs ) ? $aIPs : [];
	}
}