<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class BaseTable {

	use ModConsumer;

	const DEFAULT_PER_PAGE = 25;

	/**
	 * @var int
	 */
	protected $pageSize;

	/**
	 * @var int
	 */
	protected $totalRecords = -1;

	/**
	 * @var array
	 */
	protected $records = [];

	protected function extra_tablenav( $which ) {
		echo sprintf( '<a href="#" data-tableaction="refresh" class="btn btn-sm btn-outline-dark ml-3 tableActionRefresh">%s</a>', __( 'Refresh', 'wp-simple-firewall' ) );
	}

	/**
	 * @param object $item
	 * @param string $colName
	 * @return string
	 */
	public function column_default( $item, $colName ) {
		return $item[ $colName ];
	}

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_cb( $aItem ) {
		return sprintf( '<input type="checkbox" name="ids" value="%s" />', $aItem[ 'id' ] );
	}

	/**
	 * @return array
	 */
	public function get_sortable_columns() {
		return [];
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
	 * @return \FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable\Base
	 */
	public function prepare_items() {
		$aCols = $this->get_columns();
		$aHidden = [];
		$this->_column_headers = [ $aCols, $aHidden, $this->get_sortable_columns() ];
		$this->items = $this->getRecords();

		$this->set_pagination_args(
			[
				'total_items' => $this->getTotalRecords(),
				'per_page'    => $this->getPageSize()
			]
		);
		return $this;
	}

	/**
	 * @param string $option
	 * @param int    $default
	 * @return int
	 */
	protected function get_items_per_page( $option, $default = 20 ) {
		return $this->getPageSize();
	}

	public function single_row( $item ) {
		if ( empty( $item[ 'custom_row' ] ) ) { // it's a normal row so render as always
			parent::single_row( $item );
		}
		else {
			$this->single_row_custom( $item );
		}
	}

	/**
	 * override this in order to display a custom row
	 * @param $aItem
	 */
	public function single_row_custom( $aItem ) {
		parent::single_row( $aItem );
	}

	public function getPageSize() :int {
		return empty( $this->pageSize ) ? self::DEFAULT_PER_PAGE : $this->pageSize;
	}

	public function getRecords() :array {
		return $this->records;
	}

	public function getTotalRecords() {
		return $this->totalRecords;
	}

	/**
	 * @param array $records
	 * @return $this
	 */
	public function setRecords( array $records ) {
		$this->records = $records;
		return $this;
	}

	/**
	 * @param int $size
	 * @return $this
	 */
	public function setPageSize( $size ) {
		$this->pageSize = $size;
		return $this;
	}

	/**
	 * @param int $total
	 * @return $this
	 */
	public function setTotalRecords( int $total ) {
		$this->totalRecords = $total;
		return $this;
	}
}