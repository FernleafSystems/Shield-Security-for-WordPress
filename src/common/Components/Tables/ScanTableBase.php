<?php

if ( !class_exists( 'ICWP_BaseTable' ) ) {
	require_once( dirname( __FILE__ ).'/ICWP_BaseTable.php' );
}

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanTableBase extends ICWP_BaseTable {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_path( $aItem ) {
		return sprintf( '<code>%s</code>', $aItem[ 'path' ] );
	}

	protected function extra_tablenav( $which ) {
		echo '';
	}

	/**
	 * @return string[]
	 */
	protected function get_table_classes() {
		return array_merge( parent::get_table_classes(), [ 'scan-table' ] );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'path'       => 'File',
			'status'     => 'Status',
			'ignored'    => 'Ignored',
			'created_at' => 'Discovered',
		);
	}
}