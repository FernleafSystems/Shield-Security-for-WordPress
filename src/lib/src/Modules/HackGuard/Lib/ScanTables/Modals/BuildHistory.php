<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\Modals;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class BuildHistory {

	use ModConsumer;

	private $history = [];

	/**
	 * @param Scans\Base\ResultItem $resultItem
	 * @return string
	 * @throws \Exception
	 */
	public function run( $resultItem ) :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$results = ( new Retrieve() )
			->setMod( $this->getMod() )
			->setAdditionalWheres( [
				sprintf( "`ri`.`item_id`='%s'", $resultItem->VO->item_id )
			] )
			->retrieve();

		foreach ( $results->getItems() as $item ) {
			$vo = $item->VO;

			foreach ( $this->columnMap() as $column => $name ) {
				$ts = $vo->{$column};
				if ( $ts > 0 ) {
					$this->prepTimestamp( $ts );

					if ( $column === 'created_at' ) {
						$this->history[ $ts ][] = sprintf(
							__( "File detected as %s by %s scanner.", 'wp-simple-firewall' ),
							sprintf( '<strong>%s</strong>', $this->getItemFileStatus( $item ) ),
							sprintf( '<strong>%s</strong>', $mod->getScanCon( $item->scan )->getScanName() )
						);
					}
					else {
						$this->history[ $ts ][] = $name;
					}
				}
			}
		}

		return $this->getMod()->renderTemplate(
			'/wpadmin_pages/insights/scans/modal/scan_item_view/item_history.twig',
			[
				'flags'   => [
					'has_history' => $results->hasItems(),
				],
				'vars'    => [
					'history' => $this->convertHistoryToHuman(),
				],
				'strings' => [
				],
			]
		);
	}

	/**
	 * @param Scans\Base\ResultItem $item
	 * @return string
	 */
	private function getItemFileStatus( $item ) :string {
		if ( $item->is_unrecognised ) {
			$status = __( 'Unrecognised', 'wp-simple-firewall' );
		}
		elseif ( $item->is_mal ) {
			$status = __( 'Potential Malware', 'wp-simple-firewall' );
		}
		elseif ( $item->is_missing ) {
			$status = __( 'Missing', 'wp-simple-firewall' );
		}
		elseif ( $item->is_checksumfail ) {
			$status = __( 'Modified', 'wp-simple-firewall' );
		}
		else {
			$status = __( 'Unknown', 'wp-simple-firewall' );
		}
		return $status;
	}

	private function convertHistoryToHuman() :array {
		$WP = Services::WpGeneral();
		$humanHistory = [];
		ksort( $this->history );
		foreach ( $this->history as $ts => $history ) {
			$humanHistory[ $WP->getTimeStringForDisplay( $WP->getTimeAsGmtOffset( $ts ) ) ] = array_unique( $history );
		}
		return array_reverse( $humanHistory );
	}

	private function columnMap() :array {
		return [
			'created_at'        => __( 'File Detected By Scans' ),
			'attempt_repair_at' => __( 'File Repair Attempted' ),
			'item_repaired_at'  => __( 'File Repaired' ),
			'item_deleted_at'   => __( 'File Deleted' ),
			'ignored_at'        => __( 'Item Marked As Ignored' ),
			'notified_at'       => __( 'Notification Of Scan Detection Sent' ),
		];
	}

	private function prepTimestamp( $ts ) {
		$ts = (int)$ts;
		if ( !isset( $this->history[ $ts ] ) ) {
			$this->history[ $ts ] = [];
		}
	}
}