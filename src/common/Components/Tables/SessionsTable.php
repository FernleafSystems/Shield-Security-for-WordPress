<?php

if ( !class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH.'wp-admin/includes/class-wp-list-table.php' );
}

class SessionsTable extends WP_List_Table {

	const DEFAULT_PER_PAGE = 2;

	/**
	 * @var int
	 */
	protected $nPerPage;

	/**
	 * @var int
	 */
	protected $nTotalRecords;

	/**
	 * @var string
	 */
	protected $sAuditContext;

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
			'message'     => 'Message',
			'wp_username' => 'Username',
			'ip'          => 'IP Address',
//			'category'    => 'Category',
		);
	}

	/**
	 * @return array
	 */
	public function get_sortable_columns() {
		return array();
//		$aCols = $this->get_columns();
//		foreach ( $aCols as $sCol => $sName ) {
//			$aCols[ $sCol ] = array( $sCol, false );
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

		$this->set_pagination_args(
			array(
				'total_items' => $this->getTotalRecords(),
				'per_page'    => $this->getPerPage()
			)
		);
		return $this;
	}

	/**
	 * @return string
	 */
	public function getAuditContext() {
		return $this->sAuditContext;
	}

	/**
	 * @return array
	 */
	public function getAuditEntries() {
		return $this->aAuditEntries;
	}

	/**
	 * @param string $option
	 * @param int    $default
	 * @return int
	 */
	protected function get_items_per_page( $option, $default = 20 ) {
		return $this->getPerPage();
	}

	/**
	 * @return int
	 */
	public function getPerPage() {
		return empty( $this->nPerPage ) ? self::DEFAULT_PER_PAGE : $this->nPerPage;
	}

	/**
	 * @return int
	 */
	public function getTotalRecords() {
		return $this->nTotalRecords;
	}

	/**
	 * @param string $sAuditContext
	 * @return $this
	 */
	public function setAuditContext( $sAuditContext ) {
		$this->sAuditContext = $sAuditContext;
		return $this;
	}

	/**
	 * @param array $aAuditEntries
	 * @return $this
	 */
	public function setAuditEntries( $aAuditEntries ) {
		$this->aAuditEntries = $aAuditEntries;
		return $this;
	}

	/**
	 * @param int $nPerPage
	 * @return $this
	 */
	public function setPerPage( $nPerPage ) {
		$this->nPerPage = $nPerPage;
		return $this;
	}

	/**
	 * @param int $nTotalRecords
	 * @return $this
	 */
	public function setTotalRecords( $nTotalRecords ) {
		$this->nTotalRecords = $nTotalRecords;
		return $this;
	}
}