<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertVulnerabilities;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\ResultItem;
use FernleafSystems\Wordpress\Services\Services;

class AlertHandlerVulnerabilities extends AlertHandlerBase {

	public function alertAction() :string {
		return EmailInstantAlertVulnerabilities::class;
	}

	public function alertTitle() :string {
		return __( 'Vulnerabilities Detected', 'wp-simple-firewall' );
	}

	public function alertDataKeys() :array {
		return [
			'plugins',
			'themes',
		];
	}

	protected function run() {
		add_action( 'shield/scan_queue_completed', function () {
			$results = ( new RetrieveItems() )
				->setScanController( self::con()->comps->scans->WPV() )
				->retrieveResults( RetrieveItems::CONTEXT_NOT_YET_NOTIFIED );

			if ( $results->hasItems() ) {

				$data = [];

				$resultItemIDs = [];

				/** @var ResultItem $item */
				foreach ( $results->getAllItems() as $item ) {
					if ( $item->VO->item_type === Handler::ITEM_TYPE_PLUGIN ) {
						$resultItemIDs[] = $item->VO->resultitem_id;
						$data[ 'plugins' ] = \array_merge( $data[ 'plugins' ] ?? [], [ $item->VO->item_id ] );
					}
					if ( $item->VO->item_type === Handler::ITEM_TYPE_THEME ) {
						$resultItemIDs[] = $item->VO->resultitem_id;
						$data[ 'themes' ] = \array_merge( $data[ 'themes' ] ?? [], [ $item->VO->item_id ] );
					}
				}

				if ( !empty( $resultItemIDs ) ) {
					$updateSuccess = Services::WpDb()->doSql( sprintf(
						'UPDATE `%s` SET %s WHERE `id` IN (%s);',
						self::con()->db_con->scan_result_items->getTable(),
						sprintf( '`notified_at`=%s', Services::Request()->ts() ),
						\implode( ',', $resultItemIDs )
					) );

					if ( $updateSuccess ) {
						self::con()->comps->instant_alerts->updateAlertDataFor( $this, $data );
					}
				}
			}
		} );
	}
}