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
	}

	private function deleteTmpDir() {
		Services::WpFs()->deleteDir( $this->getCon()->getPluginCachePath() );
	}

	private function deleteDatabases() {
		$con = $this->getCon();
		$WPDB = Services::WpDb();

		// Delete all the legacy tables first (i.e. no inter-dependencies)
		array_map(
			function ( $module ) {
				foreach ( $module->getDbHandlers( true ) as $dbh ) {
					$dbh->tableDelete();
				}
			},
			[
				$con->getModule_Plugin(),
				$con->getModule_Events(),
				$con->getModule_HackGuard(),
				$con->getModule_IPs(),
				$con->getModule_Reporting(),
				$con->getModule_Sessions(),
			]
		);

		$WPDB->doDropTable(
			implode( '`,`', array_map(
					function ( $dbh ) {
						/** @var $dbh Handler */
						return $dbh->getTableSchema()->table;
					},
					[
						// Order is critical
						$con->getModule_AuditTrail()->getDbH_Meta(),
						$con->getModule_AuditTrail()->getDbH_Logs(),
						$con->getModule_Data()->getDbH_ReqLogs(),
						$con->getModule_Data()->getDbH_IPs(),
					]
				)
			)
		);
	}
}