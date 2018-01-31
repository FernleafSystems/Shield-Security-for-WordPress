<?php

if ( !class_exists( 'ICWP_BaseTable.php' ) ) {
	require_once( dirname( __FILE__ ).'/ICWP_BaseTable.php' );
}

class SessionsTable extends ICWP_BaseTable {

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'wp_username'      => 'Username',
			'logged_in_at'     => 'Logged In',
			'last_activity_at' => 'Last Activity',
			'is_secadmin'      => 'Sec. Admin',
			'ip'               => 'IP Address',
		);
	}
}