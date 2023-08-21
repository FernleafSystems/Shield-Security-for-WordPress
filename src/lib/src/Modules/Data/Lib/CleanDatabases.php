<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	AuditTrail,
	Data\ModConsumer,
	Traffic
};
use FernleafSystems\Wordpress\Services\Services;

class CleanDatabases {

	use ExecOnce;
	use ModConsumer;

	protected function run() {
		$this->cleanRequestLogs();
		$this->cleanOutUnreferencedIPs();
	}

	private function cleanOutUnreferencedIPs() {
		$con = $this->con();

		$this->cleanRequestLogs();

		/** @var Select[] $dbSelectors */
		$dbSelectors = [
			$con->getModule_Data()->getDbH_ReqLogs()->getQuerySelector(),
			$con->getModule_IPs()->getDbH_BotSignal()->getQuerySelector(),
			$con->getModule_IPs()->getDbH_IPRules()->getQuerySelector(),
		];

		// This is more work, but it optimises the array of ip_ref's so that it's not massive and then has to be "uniqued"
		$ipIDsInUse = [];
		foreach ( $dbSelectors as $dbSelector ) {
			$ipIDsInUse = \array_merge( $ipIDsInUse, $dbSelector->getDistinctForColumn( 'ip_ref' ) );
		}
		$ipIDsInUse = \array_unique( $ipIDsInUse );

		$dbhIPs = $con->getModule_Data()->getDbH_IPs();
		if ( false ) {
			// This method could potentially send 10000s of IP IDs
			$dbhIPs->getQueryDeleter()
				   ->addWhereNotIn( 'id', $ipIDsInUse )
				   ->query();
		}
		else {
			// This method is likely going to send far fewer IDs into the delete query, but requires 2 queries.
			$idsToDelete = \array_diff( $dbhIPs->getQuerySelector()->getDistinctForColumn( 'id' ), $ipIDsInUse );
			if ( !empty( $idsToDelete ) ) {
				$dbhIPs->getQueryDeleter()
					   ->addWhereIn( 'id', \array_map( '\intval', $idsToDelete ) )
					   ->query();
			}
		}

		$this->cleanUserMeta();
	}

	/**
	 * Delete all the user meta rows where there is no corresponding User ID.
	 * WARNING: GREAT CARE MUST ALWAYS BE TAKEN WHEN EDITING THIS QUERY TO ENSURE WE DELETE ONLY FROM `meta`
	 */
	private function cleanUserMeta() {
		Services::WpDb()->doSql( sprintf(
			'DELETE `meta` FROM `%s` as `meta`
				LEFT JOIN `%s` as `users` on `users`.`ID`=`meta`.`user_id` WHERE `users`.`ID` IS NULL',
			$this->con()->getModule_Data()->getDbH_UserMeta()->getTableSchema()->table,
			Services::WpDb()->getTable_Users()
		) );
//		$res = Services::WpDb()->selectCustom( sprintf(
//			'SELECT `meta`.`user_id` as `meta_user_id`, `users`.`ID` as `wp_user_id` FROM `%s` as `meta`
//				LEFT JOIN `%s` as `users` on `users`.`ID`=`meta`.`user_id` WHERE `users`.`ID` IS NULL',
//			$userMetaData->getTableSchema()->table,
//			Services::WpDb()->getTable_Users()
//		) );
	}

	private function cleanRequestLogs() {
		$con = $this->con();

		// 1. Clean Requests & Audit Trail
		// Deleting Request Logs automatically cascades to Audit Trail and then to Audit Trail Meta.
		/** @var AuditTrail\Options $optsAudit */
		$optsAudit = $con->getModule_AuditTrail()->getOptions();
		/** @var Traffic\Options $optsTraffic */
		$optsTraffic = $con->getModule_Traffic()->getOptions();

		$con->getModule_Data()
			->getDbH_ReqLogs()
			->tableCleanExpired( \max( $optsAudit->getAutoCleanDays(), $optsTraffic->getAutoCleanDays() ) );
	}
}