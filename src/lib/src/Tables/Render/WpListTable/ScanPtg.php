<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanPtg extends ScanBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_path( $aItem ) {
		$aButtons = [];
		if ( !empty( $aItem[ 'href_download' ] ) ) {
			$aButtons[] = $this->getActionButton_DownloadFile( $aItem[ 'href_download' ] );
		}
		return parent::column_path( $aItem ).$this->buildActions( $aButtons );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			'path'       => __( 'File', 'wp-simple-firewall' ),
			'status'     => __( 'Status', 'wp-simple-firewall' ),
			'created_at' => __( 'Discovered', 'wp-simple-firewall' ),
		];
	}
}