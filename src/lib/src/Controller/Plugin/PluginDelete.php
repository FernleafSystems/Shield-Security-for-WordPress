<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PluginDelete {

	use PluginControllerConsumer;

	public function run() {
		$this->deleteDatabases();
		$this->deleteTmpDir();
		$this->deleteOptions();
	}

	private function deleteOptions() {

		self::con()->opts->delete();

		foreach (
			[
				'icwp-wpsf-cs_auths',
				'icwp-wpsf-rules',
				self::con()->prefix( 'ip_rules_cache', '_' ),
			] as $opt
		) {
			Services::WpGeneral()->deleteOption( $opt );
		}
	}

	private function deleteTmpDir() {
		$path = self::con()->cache_dir_handler->dir();
		if ( !empty( $path ) ) {
			Services::WpFs()->deleteDir( $path );
		}
	}

	private function deleteDatabases() {
		$dbCon = self::con()->db_con;

		$builtInTablesToDelete = \array_unique( \array_map(
			function ( $dbh ) {
				/** @var $dbh Handler */
				$dbh::GetTableReadyCache()->setReady( $dbh->getTableSchema(), false );
				return $dbh->getTableSchema()->table;
			},
			[
				// Order is critical
				$dbCon->dbhActivityLogsMeta(),
				$dbCon->dbhActivityLogs(),
				$dbCon->dbhSnapshots(),
				$dbCon->dbhScanResults(),
				$dbCon->dbhResultItemMeta(),
				$dbCon->dbhResultItems(),
				$dbCon->dbhScanItems(),
				$dbCon->dbhScans(),
				$dbCon->dbhFileLocker(),
				$dbCon->dbhMalware(),
				$dbCon->dbhCrowdSecSignals(),
				$dbCon->dbhBotSignal(),
				$dbCon->dbhIPRules(),
				$dbCon->dbhMfa(),
				$dbCon->dbhReqLogs(),
				$dbCon->dbhUserMeta(),
				$dbCon->dbhIPMeta(),
				$dbCon->dbhIPs(),
				$dbCon->dbhEvents(),
				$dbCon->dbhReports(),
				$dbCon->dbhRules(),
			]
		) );

		/**
		 * Always signal to the DB Service that any data we may have retained about DB table readiness is purged.
		 */
		Services::WpDb()->doDropTable( \implode( '`,`', $builtInTablesToDelete ) );
		Services::WpDb()->clearResultShowTables();
		$dbCon->reset();
	}
}