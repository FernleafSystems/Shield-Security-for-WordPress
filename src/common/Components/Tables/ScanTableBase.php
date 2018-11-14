<?php

if ( !class_exists( 'ICWP_BaseTable' ) ) {
	require_once( dirname( __FILE__ ).'/ICWP_BaseTable.php' );
}

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanTableBase extends ICWP_BaseTable {

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
}