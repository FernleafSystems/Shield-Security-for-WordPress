<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

use FernleafSystems\Wordpress\Services\Services;

if ( !class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH.'wp-admin/includes/class-wp-list-table.php' );
}

class Base extends \WP_List_Table {

	const DEFAULT_PER_PAGE = 25;

	/**
	 * @var int
	 */
	protected $nPerPage;

	/**
	 * @var int
	 */
	protected $nTotalRecords;

	/**
	 * @var array
	 */
	protected $aItemEntries;

	/**
	 * It seems rendering a WP Table on an AJAX request upsets the balance of the universe
	 * an attempt to get rid of the error:  PHP Notice:  Undefined index: hook_suffix in
	 * wp-admin/includes/class-wp-screen.php on line 209
	 * @param array $aArgs
	 */
	public function __construct( $aArgs = array() ) {
		parent::__construct( array_merge( [ 'screen' => 'odp-ajax' ], $aArgs ) );
	}

	protected function extra_tablenav( $which ) {
		echo sprintf( '<a href="#" data-tableaction="refresh" class="btn btn-sm tableActionRefresh">%s</a>', _wpsf__( 'Refresh' ) );
	}

	/**
	 * @param object $aItem
	 * @param string $sColName
	 * @return string
	 */
	public function column_default( $aItem, $sColName ) {
		return $aItem[ $sColName ];
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
	 * @return string[]
	 */
	protected function get_table_classes() {
		return array_merge( parent::get_table_classes(), [ 'odp-table' ] );
	}

	/**
	 * @return $this
	 */
	public function prepare_items() {
		$aCols = $this->get_columns();
		$aHidden = array();
		$this->_column_headers = array( $aCols, $aHidden, $this->get_sortable_columns() );
		$this->items = $this->getItemEntries();

		$this->set_pagination_args(
			array(
				'total_items' => $this->getTotalRecords(),
				'per_page'    => $this->getPerPage()
			)
		);
		return $this;
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
	 * @return array
	 */
	public function getItemEntries() {
		return $this->aItemEntries;
	}

	/**
	 * @return int
	 */
	public function getTotalRecords() {
		return $this->nTotalRecords;
	}

	/**
	 * @param array $aEntries
	 * @return $this
	 */
	public function setItemEntries( $aEntries ) {
		$this->aItemEntries = $aEntries;
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

	/**
	 * @return string
	 */
	protected function getColumnHeader_Actions() {
		return '<span class="dashicons dashicons-admin-tools"></span>';
	}

	/**
	 * @param int $nId
	 * @return string
	 */
	protected function getActionButton_Delete( $nId ) {
		return sprintf( '<button title="%s"'.
						' class="btn btn-sm btn-outline-danger action delete" data-rid="%s">'.
						'<span class="dashicons dashicons-dismiss"></span></button>',
			_wpsf__( 'Delete' ), $nId );
	}

	/**
	 * @param int $nId
	 * @return string
	 */
	protected function getActionButton_Repair( $nId ) {
		return sprintf( '<button title="%s"'.
						' class="btn btn-sm btn-outline-success action repair" data-rid="%s">'.
						'<span class="dashicons dashicons-image-rotate"></span></button>',
			_wpsf__( 'Repair' ), $nId );
	}

	/**
	 * @param int $nId
	 * @return string
	 */
	protected function getActionButton_Ignore( $nId ) {
		return sprintf( '<button title="%s"'.
						' class="btn btn-sm btn-outline-info action ignore" data-rid="%s">'.
						'<span class="dashicons dashicons-hidden"></span></button>',
			_wpsf__( 'Ignore' ), $nId );
	}

	/**
	 * TODO Put this into SErvice IPs and grab it from there
	 * @param string $sIp
	 * @return string
	 */
	protected function getIpWhoisLookupLink( $sIp ) {
		return sprintf( '<a href="%s" target="_blank">%s</a>',
			Services::IP()->getIpWhoisLookup( $sIp ),
			$sIp
		);
	}
}