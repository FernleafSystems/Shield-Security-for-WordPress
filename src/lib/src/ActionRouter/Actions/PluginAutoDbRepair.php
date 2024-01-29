<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler;
use FernleafSystems\Wordpress\Services\Services;

class PluginAutoDbRepair extends BaseAction {

	public const SLUG = 'auto_db_repair';

	protected function exec() {
		$con = self::con();
		$dbCon = $con->db_con;

		// 1. Forcefully re-run all checks:
		$checks = $con->prechecks;
		$dbMisconfigured = \count( $checks[ 'dbs' ] ) !== \count( \array_filter( $checks[ 'dbs' ] ) );

		if ( $dbMisconfigured ) {
			/** @var Handler[] $allHandlers */
			$allHandlers = [
				$dbCon->dbhActivityLogs(),
				$dbCon->dbhActivityLogsMeta(),
				$dbCon->dbhIPs(),
				$dbCon->dbhIPMeta(),
				$dbCon->dbhReqLogs(),
				$dbCon->dbhUserMeta(),
				$dbCon->dbhBotSignal(),
				$dbCon->dbhIPRules(),
				$dbCon->dbhScans(),
				$dbCon->dbhScanItems(),
				$dbCon->dbhScanResults(),
				$dbCon->dbhResultItems(),
				$dbCon->dbhResultItemMeta(),
			];
			Services::WpDb()->doSql(
				sprintf( 'DROP TABLE IF EXISTS `%s`', \implode( '`,`', \array_map(
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