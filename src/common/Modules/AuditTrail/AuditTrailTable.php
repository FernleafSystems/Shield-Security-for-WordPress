<?php

if ( !class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH.'wp-admin/includes/class-wp-list-table.php' );
}

class AuditTrailTable extends WP_List_Table {

	/**
	 * @var array
	 */
	protected $aAuditEntries;

	/**
	 * @param object $aItem
	 * @param string $sColName
	 */
	public function column_default( $aItem, $sColName ) {
		return $aItem[ $sColName ];
	}
	protected function extra_tablenav( $which ) {
		echo sprintf( '<a href="#" data-tableaction="refresh" class="btn tableActionRefresh">%s</a>', _wpsf__( 'Refresh' ) );
	}

	public function get_columns() {
		return array(
			'created_at'  => 'Date',
			'event'       => 'Event',
			'category'    => 'Category',
			'ip'          => 'IP Address',
			'wp_username' => 'Username',
		);
	}

	/**
	 * @return array
	 */
	public function get_sortable_columns() {
		return array();
//		$aCols = $this->get_columns();
//		foreach ( $aCols as $sCol => $sName ) {
//			$aCols[ $sCol] = array( $sCol, false );
//		}
//		return $aCols;
	}

	/**
	 * @return $this
	 */
	public function prepare_items() {
		$aCols = $this->get_columns();
		$aHidden = array();
		$this->_column_headers = array( $aCols, $aHidden, $this->get_sortable_columns() );
		$this->items = $this->getAuditEntries();

		return $this;
	}

	/**
	 * @return array
	 */
	public function getAuditEntries() {
		return $this->aAuditEntries;
	}

	/**
	 * @param array $aAuditEntries
	 * @return $this
	 */
	public function setAuditEntries( $aAuditEntries ) {
		$this->aAuditEntries = $aAuditEntries;
		return $this;
	}
}