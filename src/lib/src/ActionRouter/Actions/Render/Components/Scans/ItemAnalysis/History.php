<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Services\Services;

class History extends Base {

	public const SLUG = 'scanitemanalysis_history';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/file_history.twig';

	private $history = [];

	protected function getRenderData() :array {
		$item = $this->getScanItem();

		$results = ( new RetrieveItems() )
			->addWheres( [
				sprintf( "`ri`.`item_id`='%s'", $item->VO->item_id )
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
							sprintf( '<strong>%s</strong>', \implode( ', ', $item->getStatusForHuman() ) ),
							sprintf( '<strong>%s</strong>',
								self::con()->comps->scans->getScanCon( $item->VO->scan )->getScanName() )
						);
					}
					else {
						$this->history[ $ts ][] = $name;
					}
				}
			}
		}

		return [
			'flags' => [
				'has_history' => $results->hasItems(),
			],
			'vars'  => [
				'history' => $this->convertHistoryToHuman(),
			],
		];
	}

	private function convertHistoryToHuman() :array {
		$WP = Services::WpGeneral();
		$humanHistory = [];
		\ksort( $this->history );
		foreach ( $this->history as $ts => $history ) {
			$humanHistory[ $WP->getTimeStringForDisplay( $WP->getTimeAsGmtOffset( $ts ) ) ] = \array_unique( $history );
		}
		return \array_reverse( $humanHistory );
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