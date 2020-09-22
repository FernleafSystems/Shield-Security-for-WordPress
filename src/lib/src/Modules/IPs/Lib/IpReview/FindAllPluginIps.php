<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpReview;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
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

		// Traffic
		/** @var Databases\Traffic\Select $sel */
		$sel = $con->getModule_Traffic()
				   ->getDbHandler_Traffic()
				   ->getQuerySelector();
		$ips = array_merge( $ips, $sel->getDistinctIps() );

		// Audit Trail
		/** @var Databases\AuditTrail\Select $sel */
		$sel = $con->getModule_AuditTrail()
				   ->getDbHandler_AuditTrail()
				   ->getQuerySelector();
		$ips = array_merge( $ips, $sel->getDistinctIps() );

		// IP Addresses
		/** @var Databases\IPs\Select $sel */
		$sel = $con->getModule_IPs()
				   ->getDbHandler_IPs()
				   ->getQuerySelector();
		$ips = array_merge( $ips, $sel->getDistinctForColumn( 'ip' ) );

		return IpListSort::Sort( array_unique( $ips ) );
	}
}