<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanMal extends ScanBase {

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_path( $item ) {
		$aButtons = [
			$this->getActionButton_Ignore( $item[ 'id' ] ),
		];
		if ( $item[ 'can_repair' ] ) {
			$aButtons[] = $this->getActionButton_Repair( $item[ 'id' ] );
		}
		else {
			$aButtons[] = $this->getActionButton_Delete( $item[ 'id' ] );
		}
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
			'ignore' => __( 'Ignore', 'wp-simple-firewall' ),
			'delete' => __( 'Delete', 'wp-simple-firewall' ),
			'repair' => __( 'Repair', 'wp-simple-firewall' ),
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