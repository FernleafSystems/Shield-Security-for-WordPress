<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class PluginDelete {

	use Shield\Modules\PluginControllerConsumer;
	use ExecOnce;

	protected function run() {
		$this->deleteDatabases();
		$this->deleteTmpDir();
		$this->deleteOptions();
	}

	private function deleteOptions() {
		self::con()->opts->delete();
	}

	private function deleteTmpDir() {
		$path = self::con()->cache_dir_handler->dir();
		if ( !empty( $path ) ) {
			Services::WpFs()->deleteDir( $path );
		}
	}

	private function deleteDatabases() {
		$con = self::con();
		Services::WpDb()->doDropTable(
			\implode( '`,`',
				\array_merge(
					\array_map(
						function ( $dbh ) {
							/** @var $dbh Handler */
							return $dbh->getTableSchema()->table;
						},
						[
							// Order is critical
							$con->getModule_AuditTrail()->getDbH_Meta(),
							$con->getModule_AuditTrail()->getDbH_Logs(),
							$con->getModule_HackGuard()->getDbH_ScanResults(),
							$con->getModule_HackGuard()->getDbH_ResultItemMeta(),
							$con->getModule_HackGuard()->getDbH_ResultItems(),
							$con->getModule_HackGuard()->getDbH_ScanItems(),
							$con->getModule_HackGuard()->getDbH_Scans(),
							$con->getModule_HackGuard()->getDbH_FileLocker(),
							$con->getModule_IPs()->getDbH_CrowdSecSignals(),
							$con->getModule_IPs()->getDbH_BotSignal(),
							$con->getModule_IPs()->getDbH_IPRules(),
							$con->getModule_Data()->getDbH_ReqLogs(),
							$con->getModule_Data()->getDbH_UserMeta(),
							$con->getModule_Data()->getDbH_IPs(),
							$con->getModule_Events()->getDbH_Events(),
							$con->getModule_Plugin()->getDbH_Reports(),
						]
					),
					[
						sprintf( '%s%s', Services::WpDb()->getPrefix(), $con->prefixOption( 'events' ) )
					]
				)
			)
		);
	}
}