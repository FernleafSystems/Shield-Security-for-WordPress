<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanApc extends ScanBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_plugin( $aItem ) {
		$aButtons = [
			$this->getActionButton_Ignore( $aItem[ 'id' ] ),
		];
		return $aItem[ 'plugin' ].$this->buildActions( $aButtons );
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		return [
			'ignore' => __( 'Ignore', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			'cb'         => '&nbsp;',
			'plugin'     => __( 'Item', 'wp-simple-firewall' ),
			'status'     => __( 'Status', 'wp-simple-firewall' ),
			'created_at' => __( 'Discovered', 'wp-simple-firewall' ),
		];
	}
}