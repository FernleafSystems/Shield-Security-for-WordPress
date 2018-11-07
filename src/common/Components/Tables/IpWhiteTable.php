<?php

if ( !class_exists( 'IpBaseTable' ) ) {
	require_once( dirname( __FILE__ ).'/IpBaseTable.php' );
}

class IpWhiteTable extends IpBaseTable {
	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'ip'             => 'IP Address',
			'label'          => 'Label',
			'last_access_at' => 'Last Access',
			'created_at'     => 'Added',
		);
	}
}