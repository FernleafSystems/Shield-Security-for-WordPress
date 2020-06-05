<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanUfc extends ScanBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_path( $aItem ) {
		$aButtons = [
			$this->getActionButton_Ignore( $aItem[ 'id' ] ),
			$this->getActionButton_Delete( $aItem[ 'id' ] ),
		];
		if ( !empty( $aItem[ 'href_download' ] ) ) {
			$aButtons[] = $this->getActionButton_DownloadFile( $aItem[ 'href_download' ] );
		}
		return parent::column_path( $aItem ).$this->buildActions( $aButtons );
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		return [
			'delete' => __( 'Delete', 'wp-simple-firewall' ),
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