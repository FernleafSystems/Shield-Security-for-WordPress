<?php

if ( !class_exists( 'ICWP_BaseTable.php' ) ) {
	require_once( dirname( __FILE__ ).'/ICWP_BaseTable.php' );
}

class LiveTrafficTable extends ICWP_BaseTable {

	protected function extra_tablenav( $which ) {
		echo sprintf( '<a href="#" data-tableaction="refresh" class="btn tableActionRefresh">%s</a>', _wpsf__( 'Refresh' ) );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'created_at' => 'Date',
			'ip'         => 'IP',
			'path'       => 'Path',
			'payload'    => 'Request Data',
			'trans'      => 'Transgression',
			//			'category'    => 'Category',
		);
	}
}