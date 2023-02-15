<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Checks\PreModulesBootCheck;
use FernleafSystems\Wordpress\Services\Services;

class PluginAutoDbRepair extends BaseAction {

	public const SLUG = 'auto_db_repair';

	protected function exec() {
		$con = $this->getCon();

		// 1. Forcefully re-run all checks:
		$checks = ( new PreModulesBootCheck() )
			->setCon( $con )
			->run( true );
		$dbMisconfigured = count( $checks[ 'dbs' ] ) !== count( array_filter( $checks[ 'dbs' ] ) );

		if ( $dbMisconfigured ) {
			$modHG = $con->getModule_HackGuard();
			/** @var Handler[] $allHandlers */
			$allHandlers = [
				$con->getModule_AuditTrail()->getDbH_Logs(),
				$con->getModule_AuditTrail()->getDbH_Meta(),
				$con->getModule_Data()->getDbH_IPs(),
				$con->getModule_Data()->getDbH_ReqLogs(),
				$con->getModule_Data()->getDbH_UserMeta(),
				$con->getModule_IPs()->getDbH_BotSignal(),
				$con->getModule_IPs()->getDbH_IPRules(),
				$modHG->getDbH_Scans(),
				$modHG->getDbH_ScanItems(),
				$modHG->getDbH_ScanResults(),
				$modHG->getDbH_ResultItems(),
				$modHG->getDbH_ResultItemMeta()
			];
			Services::WpDb()->doSql(
				sprintf( 'DROP TABLE IF EXISTS `%s`', implode( '`,`', array_map(
					function ( $schema ) {
						return $schema->getTableSchema()->table;
					},
					$allHandlers
				) ) )
			);
			foreach ( $allHandlers as $handler ) {
				$handler::GetTableReadyCache()->setReady( $handler->getTableSchema(), false );
			}
			$msg = "Tables deleted and they'll now be recreated.";
		}
		else {
			$msg = "Tables appear to be valid and haven't been repaired.";
		}

		$this->response()->action_response_data = [
			'success'     => true,
			'page_reload' => true,
			'message'     => $msg
		];
	}
}