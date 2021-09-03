<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	AuditTrail,
	Traffic
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public function getDbH_IPs() :DB\IPs\Ops\Handler {
		return $this->getDbHandler()->loadDbH( 'ips' );
	}

	public function getDbH_ReqLogs() :DB\ReqLogs\Ops\Handler {
		$this->getDbH_IPs();
		return $this->getDbHandler()->loadDbH( 'req_logs' );
	}

	protected function cleanupDatabases() {

		// 1. Clean Requests & Audit Trail
		// Deleting Request Logs automatically cascades to Audit Trail and then to Audit Trail Meta.
		/** @var AuditTrail\Options $optsAudit */
		$optsAudit = $this->getCon()->getModule_AuditTrail()->getOptions();
		/** @var Traffic\Options $optsTraffic */
		$optsTraffic = $this->getCon()->getModule_Traffic()->getOptions();
		$this->getDbH_ReqLogs()
			 ->tableCleanExpired( max( $optsAudit->getAutoCleanDays(), $optsTraffic->getAutoCleanDays() ) );

		// 2. Clean Unused IPs.
		$this->getDbH_IPs()
			 ->getQueryDeleter()
			 ->addWhere( 'id',
				 $this->getDbH_ReqLogs()
					  ->getQuerySelector()
					  ->getDistinctForColumn( 'ip_ref' ),
				 'NOT IN'
			 );
	}
}