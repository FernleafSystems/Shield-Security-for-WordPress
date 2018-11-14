<?php

if ( !class_exists( 'ICWP_BaseTable' ) ) {
	require_once( __DIR__.'/ScanTableBase.php' );
}

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanTablePtg extends ScanTableBase {

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'path'       => 'File',
			'status'     => 'Status',
			'created_at' => 'Discovered',
		);
	}
}