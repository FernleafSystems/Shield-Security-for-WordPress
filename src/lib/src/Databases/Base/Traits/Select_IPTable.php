<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\IpListSort;

trait Select_IPTable {

	/**
	 * @return string[]
	 */
	public function getDistinctIps() :array {
		$ips = $this->getDistinctForColumn( 'ip' );
		if ( $this->getDbH()->getTableSchema()->is_ip_binary ) {
			$ips = array_filter( array_map(
				function ( $binaryIP ) {
					return empty( $binaryIP ) ? '' : inet_ntop( $binaryIP );
				},
				$ips
			) );
		}
		return IpListSort::Sort( $ips );
	}
}