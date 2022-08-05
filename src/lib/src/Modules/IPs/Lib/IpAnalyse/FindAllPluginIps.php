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

		/** @var Select $sel */
		$sel = $con->getModule_Data()
				   ->getDbH_IPs()
				   ->getQuerySelector();

		$ips = array_filter(
			array_unique( $sel->getDistinctIps() ),
			function ( $ip ) use ( $ipFilter ) {
				return empty( $ipFilter ) || strpos( $ip, $ipFilter ) !== false;
			}
		);
		return IpListSort::Sort( $ips );
	}
}