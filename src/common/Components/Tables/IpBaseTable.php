<?php

if ( !class_exists( 'ICWP_BaseTable' ) ) {
	require_once( dirname( __FILE__ ).'/ICWP_BaseTable.php' );
}

class IpBaseTable extends ICWP_BaseTable {

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'ip'             => 'IP Address',
			'label'          => 'Label',
			'transgressions' => 'Transgressions',
			'list'           => 'List',
			'last_access_at' => 'Last Access',
			'created_at'     => 'Date',
		);
	}
}