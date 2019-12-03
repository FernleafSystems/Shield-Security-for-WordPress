<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanAggregate extends ScanBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_path( $aItem ) {
		$aButtons = [
			$this->getActionButton_Ignore( $aItem[ 'id' ] ),
			$this->getActionButton_Repair( $aItem[ 'id' ] ),
		];
		if ( !empty( $aItem[ 'href_download' ] ) ) {
			$aButtons[] = $this->getActionButton_DownloadFile( $aItem[ 'href_download' ] );
		}
		return parent::column_path( $aItem ).$this->buildActions( $aButtons );
	}

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_status( $aItem ) {
		$sStatus = sprintf( '<strong>%s</strong>', $aItem[ 'status' ] );
		if ( !empty( $aItem[ 'explanation' ] ) ) {
			$sStatus .= '<ul><li>'.implode( '</li><li>', $aItem[ 'explanation' ] ).'</li></ul>';
		}
		return $sStatus;
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