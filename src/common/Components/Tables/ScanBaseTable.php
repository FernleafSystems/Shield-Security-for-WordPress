<?php

if ( !class_exists( 'ICWP_BaseTable' ) ) {
	require_once( dirname( __FILE__ ).'/ICWP_BaseTable.php' );
}

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanBaseTable extends ICWP_BaseTable {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_actions( $aItem ) {
		return sprintf( '<a href="#" class="btn btn-sm btn-outline-danger delete" title="%s" data-rid="%s">'.
						'<span class="dashicons dashicons-dismiss"></span></a>', _wpsf__( 'Delete' ), $aItem[ 'id' ] );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'path_fragment' => 'File',
			'status'        => 'Status',
			'ignored'       => 'Ignored',
			'created_at'    => 'Discovered',
		);
	}

	/**
	 * @return string
	 */
	protected function getColumnHeader_Actions() {
		return '<span class="dashicons dashicons-admin-tools"></span>';
	}
}