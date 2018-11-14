<?php

if ( !class_exists( 'ICWP_BaseTable' ) ) {
	require_once( __DIR__.'/ScanTableBase.php' );
}

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanTableWcf extends ScanTableBase {

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'path_fragment' => 'File',
			'status'        => 'Status',
			'created_at'    => 'Discovered',
		);
	}
}