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

	public function getDbH_UserMeta() :DB\UserMeta\Ops\Handler {
		return $this->getDbHandler()->loadDbH( 'user_meta' );
	}

	public function getDbH_ReqLogs() :DB\ReqLogs\Ops\Handler {
		$this->getDbH_IPs();
		return $this->getDbHandler()->loadDbH( 'req_logs' );
	}

	protected function cleanupDatabases() {
		$con = $this->getCon();

		// 1. Clean Requests & Audit Trail
		// Deleting Request Logs automatically cascades to Audit Trail and then to Audit Trail Meta.
		/** @var AuditTrail\Options $optsAudit */
		$optsAudit = $con->getModule_AuditTrail()->getOptions();
		/** @var Traffic\Options $optsTraffic */
		$optsTraffic = $con->getModule_Traffic()->getOptions();
		$this->getDbH_ReqLogs()
			 ->tableCleanExpired( max( $optsAudit->getAutoCleanDays(), $optsTraffic->getAutoCleanDays() ) );

		// 2. Clean Unused IPs.
		$this->getDbH_IPs()
			 ->getQueryDeleter()
			 ->addWhereNotIn( 'id',
				 array_unique( array_merge(
					 $this->getDbH_ReqLogs()
						  ->getQuerySelector()
						  ->getDistinctForColumn( 'ip_ref' ),
					 $con->getModule_IPs()
						 ->getDbH_BotSignal()
						 ->getQuerySelector()
						 ->getDistinctForColumn( 'ip_ref' )
				 ) )
			 )
			 ->query();
	}
}