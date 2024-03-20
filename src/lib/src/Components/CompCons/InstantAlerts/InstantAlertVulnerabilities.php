<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertVulnerabilities;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\ResultItem;
use FernleafSystems\Wordpress\Services\Services;

class InstantAlertVulnerabilities extends InstantAlertBase {

	public function __construct() {
		$this->alertActionData = [
			'plugins' => [],
			'themes'  => [],
		];
	}

	protected function alertAction() :string {
		return EmailInstantAlertVulnerabilities::class;
	}

	protected function alertTitle() :string {
		return __( 'Vulnerabilities Detected', 'wp-simple-firewall' );
	}

	protected function run() {
		parent::run();

		add_action( 'shield/scan_queue_completed', function () {
			$con = self::con();

			$results = ( new RetrieveItems() )
				->setScanController( $con->comps->scans->WPV() )
				->retrieveResults( RetrieveItems::CONTEXT_NOT_YET_NOTIFIED );

			if ( $results->hasItems() ) {
				$resultItemIDs = [];
				/** @var ResultItem $item */
				foreach ( $results->getAllItems() as $item ) {
					if ( $item->VO->item_type === Handler::ITEM_TYPE_PLUGIN ) {
						$resultItemIDs[] = $item->VO->resultitem_id;
						$this->alertActionData[ 'plugins' ][] = $item->VO->item_id;
					}
					if ( $item->VO->item_type === Handler::ITEM_TYPE_THEME ) {
						$resultItemIDs[] = $item->VO->resultitem_id;
						$this->alertActionData[ 'themes' ][] = $item->VO->item_id;
					}
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