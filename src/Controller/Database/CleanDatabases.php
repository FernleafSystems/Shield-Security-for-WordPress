<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Database;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Select;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\{
	Mfa\Ops as MfaDB,
	Reports\Ops as ReportsDB,
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogRetentionPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\Email;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogRetentionPolicy;
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
		( new CleanMalwareDB() )->run();
		$this->cleanRequestLogs();
		$this->purgeUnreferencedIPs();
		$this->purgeNotRequiredDBs();
	}

	public function cleanBotSignals() :void {
		self::con()
			->db_con
			->bot_signals
			->getQueryDeleter()
			->addWhereOlderThan( Services::Request()->carbon( true )->subWeeks( 2 )->timestamp, 'updated_at' )
			->query();
	}

	private function cleanRequestLogs() :void {
		$this->cleanActivityLogsByPolicy();
		$this->cleanUnreferencedRequestLogsByPolicy();
	}

	private function cleanActivityLogsByPolicy() :void {
		$con = self::con();
		$policy = new ActivityLogRetentionPolicy();
		$retentionByEvent = $policy->retentionSecondsByEvent();
		$now = Services::Request()->ts();

		$groupedEvents = [];
		foreach ( $retentionByEvent as $event => $seconds ) {
			$groupedEvents[ \max( \HOUR_IN_SECONDS, (int)$seconds ) ][] = $event;
		}

		foreach ( $groupedEvents as $seconds => $events ) {
			$con->db_con
				->activity_logs
				->getQueryDeleter()
				->addWhereOlderThan( $now - $seconds )
				->addWhereIn( 'event_slug', $events )
				->query();
		}

		$deleter = $con->db_con
					   ->activity_logs
					   ->getQueryDeleter()
					   ->addWhereOlderThan( $now - $policy->defaultRetentionSeconds() );

		if ( !empty( $retentionByEvent ) ) {
			$deleter->addWhereNotIn( 'event_slug', \array_keys( $retentionByEvent ) );
		}

		$deleter->query();
	}

	private function cleanUnreferencedRequestLogsByPolicy() :void {
		$retention = ( new RequestLogRetentionPolicy() )->retentionSeconds();
		$now = Services::Request()->ts();

		$this->deleteUnreferencedRequestLogsOlderThan(
			$now - $retention[ 'transient' ],
			true
		);
		$this->deleteUnreferencedRequestLogsOlderThan(
			$now - $retention[ 'standard' ],
			false
		);
	}

	private function deleteUnreferencedRequestLogsOlderThan( int $cutoffTimestamp, bool $transient ) :void {
		$con = self::con();
		Services::WpDb()->doSql( sprintf(
			'DELETE FROM `%1$s`
				WHERE `created_at` < %3$d
					AND `transient` = %4$d
					AND NOT EXISTS (
						SELECT 1
						FROM `%2$s` AS `activity`
						WHERE `activity`.`req_ref` = `%1$s`.`id`
					);',
			$con->db_con->req_logs->getTable(),
			$con->db_con->activity_logs->getTable(),
			$cutoffTimestamp,
			$transient ? 1 : 0
		) );
	}

	public function cleanStaleReports() :void {
		/** @var ReportsDB\Delete $deleter */
		$deleter = self::con()->db_con->reports->getQueryDeleter();
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
			self::con()->db_con->user_meta->getTable(),
			Services::WpDb()->getTable_Users()
		) );
	}

	private function cleanOldEmail2FA() {
		/** @var MfaDB\Delete $deleter */
		$deleter = self::con()->db_con->mfa->getQueryDeleter();
		$deleter->filterBySlug( Email::ProviderSlug() )
				->addWhereOlderThan( Services::Request()->carbon()->subMinutes( 10 )->timestamp )
				->query();
	}

	public function purgeNotRequiredDBs() :void {
		$con = self::con();
		if ( empty( $con->comps->file_locker->getFilesToLock() ) ) {
			$con->comps->file_locker->purge();
		}
	}

	public function purgeUnreferencedIPs() :void {
		$con = self::con();

		// This is more work, but it optimises the array of ip_ref's so that it's not massive and then has to be "uniqued"
		$ipIDsInUse = [];
		foreach (
			[
				$con->db_con->req_logs->getQuerySelector(),
				$con->db_con->bot_signals->getQuerySelector(),
				$con->db_con->ip_rules->getQuerySelector(),
				$con->db_con->user_meta->getQuerySelector(),
			] as $dbSelector
		) {
			/** @var Select $dbSelector */
			$ipIDsInUse = \array_unique( \array_merge(
				$ipIDsInUse,
				\array_map( '\intval', $dbSelector->getDistinctForColumn( 'ip_ref' ) )
			) );
		}

		$con->db_con->ips->getQueryDeleter()->addWhereNotIn( 'id', $ipIDsInUse )->query();
	}
}
