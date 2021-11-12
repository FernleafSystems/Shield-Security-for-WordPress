<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\Ops\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\IpListSort;

class FindAllPluginIps {

	use PluginControllerConsumer;

	public function run( string $ipFilter = '' ) :array {
		$con = $this->getCon();

		// User Sessions
		/** @var Databases\Session\Select $sel */
		$sel = $con->getModule_Sessions()
				   ->getDbHandler_Sessions()
				   ->getQuerySelector();
		$ips = $sel->getDistinctIps();

		/** @var Select $sel */
		$sel = $con->getModule_Data()
				   ->getDbH_IPs()
				   ->getQuerySelector();
		$ips = array_merge( $ips, $sel->getDistinctIps() );

		// IP Addresses
		/** @var Databases\IPs\Select $sel */
		$sel = $con->getModule_IPs()
				   ->getDbHandler_IPs()
				   ->getQuerySelector();
		$ips = array_merge( $ips, $sel->getDistinctForColumn( 'ip' ) );

		$ips = array_unique( $ips );
		if ( !empty( $ipFilter ) ) {
			$ips = array_filter( $ips, function ( $ip ) use ( $ipFilter ) {
				return is_int( strpos( $ip, $ipFilter ) );
			} );
		}
		return IpListSort::Sort( $ips );
	}
}