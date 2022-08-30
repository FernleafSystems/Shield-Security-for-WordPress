<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	AuditTrail,
	Data,
	Traffic
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;

class CleanDatabases extends ExecOnceModConsumer {

	protected function run() {
		$con = $this->getCon();
		/** @var Data\ModCon $mod */
		$mod = $this->getMod();

		// 1. Clean Requests & Audit Trail
		// Deleting Request Logs automatically cascades to Audit Trail and then to Audit Trail Meta.
		/** @var AuditTrail\Options $optsAudit */
		$optsAudit = $con->getModule_AuditTrail()->getOptions();
		/** @var Traffic\Options $optsTraffic */
		$optsTraffic = $con->getModule_Traffic()->getOptions();
		$mod->getDbH_ReqLogs()
			->tableCleanExpired( max( $optsAudit->getAutoCleanDays(), $optsTraffic->getAutoCleanDays() ) );

		/** @var Select[] $dbSelectors */
		$dbSelectors = [
			$mod->getDbH_ReqLogs()->getQuerySelector(),
			$con->getModule_IPs()->getDbH_BotSignal()->getQuerySelector(),
			$con->getModule_IPs()->getDbH_IPRules()->getQuerySelector(),
		];

		// This is more work, but it optimises the array of ip_ref's so that it's not massive and then has to be "uniqued"
		$ipIDs = [];
		foreach ( $dbSelectors as $dbSelector ) {
			if ( !empty( $ipIDs ) ) {
				$dbSelector->addWhereNotIn( 'ip_ref', $ipIDs );
			}
			$ipIDs = array_merge( $ipIDs, $dbSelector->getDistinctForColumn( 'ip_ref' ) );
		}

		// 2. Clean Unused IPs.
		$mod->getDbH_IPs()
			->getQueryDeleter()
			->addWhereNotIn( 'id', $ipIDs )
			->query();

		// TODO 3. Clean User Meta.
	}
}