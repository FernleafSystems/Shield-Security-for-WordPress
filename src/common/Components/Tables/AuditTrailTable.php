<?php

if ( !class_exists( 'ICWP_BaseTable.php' ) ) {
	require_once( dirname( __FILE__ ).'/ICWP_BaseTable.php' );
}

class AuditTrailTable extends ICWP_BaseTable {

	/**
	 * @var string
	 */
	protected $sAuditContext;

	protected function extra_tablenav( $which ) {
		echo sprintf( '<a href="#" data-tableaction="refresh" class="btn tableActionRefresh">%s</a>', _wpsf__( 'Refresh' ) );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'created_at'  => 'Date',
			'event'       => 'Event',
			'message'     => 'Message',
			'wp_username' => 'Username',
			'ip'          => 'IP Address',
//			'category'    => 'Category',
		);
	}

	/**
	 * @return string
	 */
	public function getAuditContext() {
		return $this->sAuditContext;
	}

	/**
	 * @param string $sAuditContext
	 * @return $this
	 */
	public function setAuditContext( $sAuditContext ) {
		$this->sAuditContext = $sAuditContext;
		return $this;
	}
}