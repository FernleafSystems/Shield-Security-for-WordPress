<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\InstantAlertVulnerabilities;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Email\EmailVO;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Enum\EnumModules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\ResultItem;
use FernleafSystems\Wordpress\Services\Services;

class InstantAlertsCon {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return self::con()->comps->opts_lookup->isModEnabled( EnumModules::PLUGIN )
			   && \count( self::con()->opts->optGet( 'instant_alerts' ) ) > 0;
	}

	protected function run() {
		foreach ( self::con()->opts->optGet( 'instant_alerts' ) as $alert ) {
			$this->{$alert}();
		}
	}

	private function vulnerabilities() {
		add_action( 'shield/scan_queue_completed', function () {
			$con = self::con();

			$results = ( new RetrieveItems() )
				->setScanController( $con->comps->scans->WPV() )
				->retrieveResults( RetrieveItems::CONTEXT_NOT_YET_NOTIFIED );

			if ( $results->hasItems() ) {
				$resultItemIDs = [];
				$plugins = [];
				$themes = [];
				/** @var ResultItem $item */
				foreach ( $results->getAllItems() as $item ) {
					if ( $item->VO->item_type === Handler::ITEM_TYPE_PLUGIN ) {
						$resultItemIDs[] = $item->VO->resultitem_id;
						$plugins[] = $item->VO->item_id;
					}
					if ( $item->VO->item_type === Handler::ITEM_TYPE_THEME ) {
						$resultItemIDs[] = $item->VO->resultitem_id;
						$themes[] = $item->VO->item_id;
					}
				}

				if ( !empty( $plugins ) || !empty( $themes ) ) {
					$con->email_con->sendVO(
						EmailVO::Factory(
							$con->comps->opts_lookup->getReportEmail(),
							__( 'Instant Alert: Vulnerabilities Detected', 'wp-simple-firewall' ),
							$con->action_router->render( InstantAlertVulnerabilities::class, [
								'plugins' => $plugins,
								'themes'  => $themes,
							] )
						)
					);
				}

				if ( !empty( $resultItemIDs ) ) {
					Services::WpDb()->doSql( sprintf(
						'UPDATE `%s` SET %s WHERE `id` IN (%s);',
						$con->db_con->scan_result_items->getTable(),
						sprintf( '`notified_at`=%s', Services::Request()->ts() ),
						\implode( ',', $resultItemIDs )
					) );
				}
			}
		} );
	}
}