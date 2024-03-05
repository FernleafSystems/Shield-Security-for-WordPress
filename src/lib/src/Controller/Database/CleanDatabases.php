<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Database;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Select;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	Mfa\Ops as MfaDB,
	Reports\Ops as ReportsDB,
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanDatabases {

	use PluginControllerConsumer;

	public function all() {
		( new CleanIpRules() )->all();
		$this->cleanBotSignals();
		$this->cleanUserMeta();
		$this->cleanOldEmail2FA();
		$this->cleanStaleReports();
		( new CleanScansDB() )->run();
		$this->cleanRequestLogs();
		$this->purgeUnreferencedIPs();
	}

	public function cleanBotSignals() :void {
		self::con()
			->db_con
			->dbhBotSignal()
			->getQueryDeleter()
			->addWhereOlderThan( Services::Request()->carbon( true )->subweeks( 2 )->timestamp, 'updated_at' )
			->query();
	}

	private function cleanRequestLogs() {
		$con = self::con();
		$comps = $con->comps;

		// 1. Clean Requests & Audit Trail
		// Deleting Request Logs automatically cascades to Audit Trail and then to Audit Trail Meta.

		$con->db_con
			->dbhReqLogs()
			->deleteRowsOlderThan(
				Services::Request()
						->carbon( true )
						->startOfDay()
						->subDays(
							\max( $comps->requests_log->getAutoCleanDays(), $comps->activity_log->getAutoCleanDays() )
						)->timestamp
			);

		// 2. Delete transient logs older than 1 hr.
		$con->db_con
			->dbhReqLogs()
			->getQueryDeleter()
			->addWhereOlderThan( Services::Request()->carbon( true )->subHour()->timestamp )
			->addWhereEquals( 'transient', '1' )
			->query();

		// 3. Delete traffic logs past their TTL that aren't referenced by activity logs.
		if ( $comps->opts_lookup->enabledTrafficLogger()
			 && $comps->requests_log->getAutoCleanDays() < $con->comps->activity_log->getAutoCleanDays() ) {
			$oldest = Services::Request()
							  ->carbon( true )
							  ->startOfDay()
							  ->subDays( $comps->requests_log->getAutoCleanDays() )->timestamp;
			Services::WpDb()->doSql(
				sprintf( 'DELETE FROM `%s` WHERE `created_at` < %s AND `id` NOT IN ( %s );',
					$con->db_con->dbhReqLogs()->getTableSchema()->table,
					$oldest,
					sprintf( 'SELECT DISTINCT `req_ref` FROM `%s` WHERE `created_at` < %s',
						$con->db_con->dbhActivityLogs()->getTableSchema()->table,
						$oldest
					)
				)
			);
		}
	}

	public function cleanStaleReports() :void {
		/** @var ReportsDB\Delete $deleter */
		$deleter = self::con()->db_con->dbhReports()->getQueryDeleter();
		$deleter->filterByProtected( false )
				->addWhereOlderThan( Services::Request()->carbon( true )->startOfDay()->subDay()->timestamp )
				->query();
	}

	/**
	 * Delete all the user meta rows where there is no corresponding User ID.
	 * WARNING: GREAT CARE MUST ALWAYS BE TAKEN WHEN EDITING THIS QUERY TO ENSURE WE DELETE ONLY FROM `meta`
	 */
	private function cleanUserMeta() {
		Services::WpDb()->doSql( sprintf(
			'DELETE `meta` FROM `%s` as `meta`
				LEFT JOIN `%s` as `users` on `users`.`ID`=`meta`.`user_id`
				WHERE `users`.`ID` IS NULL;',
			self::con()->db_con->dbhUserMeta()->getTableSchema()->table,
			Services::WpDb()->getTable_Users()
		) );
	}

	private function cleanOldEmail2FA() {
		/** @var MfaDB\Delete $deleter */
		$deleter = self::con()
			->db_con
			->dbhMfa()
			->getQueryDeleter();
		$deleter->filterBySlug( Email::ProviderSlug() )
				->addWhereOlderThan( Services::Request()->carbon()->subMinutes( 10 )->timestamp )
				->query();
	}

	public function purgeUnreferencedIPs() :void {
		$con = self::con();

		// This is more work, but it optimises the array of ip_ref's so that it's not massive and then has to be "uniqued"
		$ipIDsInUse = [];
		foreach (
			[
				$con->db_con->dbhReqLogs()->getQuerySelector(),
				$con->db_con->dbhBotSignal()->getQuerySelector(),
				$con->db_con->dbhIPRules()->getQuerySelector(),
				$con->db_con->dbhUserMeta()->getQuerySelector(),
			] as $dbSelector
		) {
			/** @var Select $dbSelector */
			$ipIDsInUse = \array_unique( \array_merge(
				$ipIDsInUse,
				\array_map( '\intval', $dbSelector->getDistinctForColumn( 'ip_ref' ) )
			) );
		}

		$con->db_con
			->dbhIPs()
			->getQueryDeleter()
			->addWhereNotIn( 'id', $ipIDsInUse )
			->query();
	}
}