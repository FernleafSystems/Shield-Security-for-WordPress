<?php

if ( !class_exists( 'ICWP_BaseTable' ) ) {
	require_once( dirname( __FILE__ ).'/ICWP_BaseTable.php' );
}

class LiveTrafficTable extends ICWP_BaseTable {

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'path'         => 'Page',
			'visitor'      => 'Visitor Details',
			'request_info' => 'Response Info',
			'created_at'   => 'Date',
		);
	}
}