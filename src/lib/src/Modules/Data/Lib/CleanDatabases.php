<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	AuditTrail,
	Data,
	HackGuard,
	IPs,
	Plugin,
	Traffic
};
use FernleafSystems\Wordpress\Services\Services;

class CleanDatabases {

	use ExecOnce;
	use Data\ModConsumer;

	protected function run() {
		$this->cleanRequestLogs();
		$this->cleanIpRules();
		$this->cleanBotSignals();
		$this->cleanUserMeta();
		$this->cleanStaleReports();
		$this->cleanStaleScans();
		$this->purgeUnreferencedIPs();
	}

	public function cleanBotSignals() :void {
		self::con()
			->getModule_IPs()
			->getDbH_BotSignal()
			->getQueryDeleter()
			->addWhereOlderThan( Services::Request()->carbon( true )->subweeks( 2 )->timestamp, 'updated_at' )
			->query();
	}

	public function cleanIpRules() :void {
		( new IPs\DB\IpRules\CleanIpRules() )->execute();
	}

	private function cleanRequestLogs() {
		$con = $this->con();

		// 1. Clean Requests & Audit Trail
		// Deleting Request Logs automatically cascades to Audit Trail and then to Audit Trail Meta.
		/** @var AuditTrail\Options $optsAudit */
		$optsAudit = $con->getModule_AuditTrail()->opts();
		/** @var Traffic\Options $optsTraffic */
		$optsTraffic = $con->getModule_Traffic()->opts();

		$con->getModule_Data()
			->getDbH_ReqLogs()
			->tableCleanExpired( \max( $optsAudit->getAutoCleanDays(), $optsTraffic->getAutoCleanDays() ) );
	}

	public function cleanStaleReports() :void {
		/** @var Plugin\DB\Report\Ops\Delete $deleter */
		$deleter = self::con()->getModule_Plugin()->getDbH_ReportLogs()->getQueryDeleter();
		$deleter->filterByType( Plugin\Lib\Reporting\Constants::REPORT_TYPE_ADHOC )
				->addWhereOlderThan( Services::Request()->carbon( true )->subDay()->timestamp )
				->query();
	}

	public function cleanStaleScans() :void {
		( new HackGuard\DB\Utility\Clean() )->execute();
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
	}

	public function purgeUnreferencedIPs() :void {
		$con = self::con();

		/** @var Select[] $dbSelectors */
		$dbSelectors = [
			'req'   => $con->getModule_Data()->getDbH_ReqLogs()->getQuerySelector(),
			'bot'   => $con->getModule_IPs()->getDbH_BotSignal()->getQuerySelector(),
			'rules' => $con->getModule_IPs()->getDbH_IPRules()->getQuerySelector(),
			'user'  => $con->getModule_Data()->getDbH_UserMeta()->getQuerySelector(),
		];

		// This is more work, but it optimises the array of ip_ref's so that it's not massive and then has to be "uniqued"
		$ipIDsInUse = [];
		foreach ( $dbSelectors as $dbSelector ) {
			$ipIDsInUse = \array_unique( \array_merge(
				$ipIDsInUse,
				\array_map( '\intval', $dbSelector->getDistinctForColumn( 'ip_ref' ) )
			) );
		}

		$con->getModule_Data()
			->getDbH_IPs()
			->getQueryDeleter()
			->addWhereNotIn( 'id', $ipIDsInUse )
			->query();
	}
}