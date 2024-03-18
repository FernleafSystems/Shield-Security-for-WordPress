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
				$dbCon->activity_logs,
				$dbCon->activity_logs_meta,
				$dbCon->ips,
				$dbCon->ip_meta,
				$dbCon->req_logs,
				$dbCon->user_meta,
				$dbCon->bot_signals,
				$dbCon->ip_rules,
				$dbCon->scans,
				$dbCon->scan_items,
				$dbCon->scan_results,
				$dbCon->scan_result_items,
				$dbCon->scan_result_item_meta,
			];
			Services::WpDb()->doSql(
				sprintf( 'DROP TABLE IF EXISTS `%s`', \implode( '`,`', \array_map(
					function ( $schema ) {
						return $schema->getTable();
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