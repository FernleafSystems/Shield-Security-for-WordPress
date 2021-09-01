<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\IPs\Ops\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\IpListSort;

class FindAllPluginIps {

	use PluginControllerConsumer;

	public function run() :array {
		$con = $this->getCon();

		// User Sessions
		/** @var Databases\Session\Select $sel */
		$sel = $con->getModule_Sessions()
				   ->getDbHandler_Sessions()
				   ->getQuerySelector();
		$ips = $sel->getDistinctIps();

		/** @var Select $sel */
		$sel = $con->getModule_Plugin()
				   ->getDbH_IPs()
				   ->getQuerySelector();
		$ips = array_merge( $ips, $sel->getDistinctIps() );

		// IP Addresses
		/** @var Databases\IPs\Select $sel */
		$sel = $con->getModule_IPs()
				   ->getDbHandler_IPs()
				   ->getQuerySelector();
		$ips = array_merge( $ips, $sel->getDistinctForColumn( 'ip' ) );

		// Bot Signal
		/** @var Databases\BotSignals\Select $sel */
		$sel = $con->getModule_IPs()
				   ->getDbHandler_BotSignals()
				   ->getQuerySelector();
		$ips = array_merge( $ips, $sel->getDistinctIps() );

		return IpListSort::Sort( array_unique( $ips ) );
	}
}