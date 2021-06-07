<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanWcf extends ScanBase {

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_path( $item ) {
		$aButtons = [
			$this->getActionButton_Ignore( $item[ 'id' ] ),
			$this->getActionButton_Repair( $item[ 'id' ] ),
		];
		if ( !empty( $item[ 'href_download' ] ) ) {
			$aButtons[] = $this->getActionButton_DownloadFile( $item[ 'href_download' ] );
		}
		return parent::column_path( $item ).$this->buildActions( $aButtons );
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		return [
			'repair' => __( 'Repair', 'wp-simple-firewall' ),
			'ignore' => __( 'Ignore', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array_merge(
			[ 'cb' => '&nbsp;' ],
			parent::get_columns()
		);
	}
}