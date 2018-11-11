<?php

if ( !class_exists( 'ICWP_BaseTable' ) ) {
	require_once( dirname( __FILE__ ).'/ICWP_BaseTable.php' );
}

class IpBaseTable extends ICWP_BaseTable {

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
			'ip'             => 'IP Address',
			'label'          => 'Label',
			'transgressions' => 'Transgressions',
			'list'           => 'List',
			'last_access_at' => 'Last Access',
			'created_at'     => 'Date',
		);
	}

	/**
	 * @return string
	 */
	protected function getColumnHeader_Actions() {
		return '<span class="dashicons dashicons-admin-tools"></span>';
	}
}