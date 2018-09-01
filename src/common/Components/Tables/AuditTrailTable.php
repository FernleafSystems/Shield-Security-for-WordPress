<?php

if ( !class_exists( 'ICWP_BaseTable.php' ) ) {
	require_once( dirname( __FILE__ ).'/ICWP_BaseTable.php' );
}

class AuditTrailTable extends ICWP_BaseTable {

	/**
	 * @var string
	 */
	protected $sAuditContext;

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_message( $aItem ) {
		return sprintf( '<textarea readonly rows="%s">%s</textarea>',
			max( 2, (int)( strlen( $aItem[ 'message' ] )/50 ) ),
			sanitize_textarea_field( $aItem[ 'message' ] )
		);
	}

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