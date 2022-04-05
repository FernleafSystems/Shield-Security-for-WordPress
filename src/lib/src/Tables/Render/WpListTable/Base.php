<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

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
	 * @ array
	 */
	protected $aItemEntries;

	/**
	 * It seems rendering a WP Table on an AJAX request upsets the balance of the universe
	 * an attempt to get rid of the error:  PHP Notice:  Undefined index: hook_suffix in
	 * wp-admin/includes/class-wp-screen.php on line 209
	 * @param array $aArgs
	 */
	public function __construct( $aArgs = [] ) {
		parent::__construct( array_merge( [ 'screen' => 'odp-ajax' ], $aArgs ) );
	}

	protected function extra_tablenav( $which ) {
		echo sprintf( '<a href="#" data-tableaction="refresh" class="btn btn-sm btn-outline-dark ms-3 tableActionRefresh">%s</a>', __( 'Refresh', 'wp-simple-firewall' ) );
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
	 * @param array $item
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids" value="%s" />', $item[ 'id' ] );
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
	 * @return $this
	 */
	public function prepare_items() {
		$aCols = $this->get_columns();
		$aHidden = [];
		$this->_column_headers = [ $aCols, $aHidden, $this->get_sortable_columns() ];
		$this->items = $this->getItemEntries();

		$this->set_pagination_args(
			[
				'total_items' => $this->getTotalRecords(),
				'per_page'    => $this->getPerPage()
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
		return $this->getPerPage();
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
	 * @param array $aButtons
	 * @return string
	 */
	protected function buildActions( $aButtons ) {
		return sprintf( '<div class="actions-block">%s</div>', implode( ' | ', (array)$aButtons ) );
	}

	protected function buildActionButton_CustomArray( array $aProps ) :string {
		$sTitle = empty( $aProps[ 'title' ] ) ? $aProps[ 'text' ] : $aProps[ 'title' ];

		$aClasses = $aProps[ 'classes' ];
		if ( in_array( 'disabled', $aClasses ) ) {
			$aClasses[] = 'text-dark';
		}

		$aDataAttrs = [];
		foreach ( $aProps[ 'data' ] as $sKey => $sValue ) {
			$aDataAttrs[] = sprintf( 'data-%s="%s"', $sKey, $sValue );
		}
		return sprintf( '<button title="%s" class="btn btn-sm btn-link %s" %s>%s</button>',
			$sTitle, implode( ' ', array_unique( $aClasses ) ), implode( ' ', $aDataAttrs ), $aProps[ 'text' ] );
	}

	/**
	 * @param array  $classes
	 * @param array  $data
	 * @param string $text
	 * @param string $title
	 * @return string
	 */
	protected function buildActionButton_Custom( $text, $classes, $data, $title = '' ) :string {
		$classes[] = 'action';
		return $this->buildActionButton_CustomArray( [
			'text'    => $text,
			'classes' => $classes,
			'data'    => $data,
			'title'   => $title
		] );
	}

	/**
	 * @return string
	 */
	protected function getColumnHeader_Actions() {
		return '<span class="dashicons dashicons-admin-tools"></span>';
	}

	/**
	 * @param int    $nId
	 * @param string $sText
	 * @return string
	 */
	protected function getActionButton_Delete( $nId, $sText = null ) {
		return $this->buildActionButton_Custom(
			empty( $sText ) ? __( 'Delete', 'wp-simple-firewall' ) : $sText,
			[ 'delete', 'text-danger' ],
			[ 'rid' => $nId, ]
		);
	}

	/**
	 * @param int $nId
	 * @return string
	 */
	protected function getActionButton_Repair( $nId ) {
		return $this->buildActionButton_Custom(
			__( 'Repair', 'wp-simple-firewall' ),
			[ 'repair', 'text-success' ],
			[ 'rid' => $nId, ]
		);
	}

	/**
	 * @param int $nId
	 * @return string
	 */
	protected function getActionButton_Ignore( $nId ) {
		return $this->buildActionButton_Custom(
			__( 'Ignore', 'wp-simple-firewall' ),
			[ 'ignore' ],
			[ 'rid' => $nId, ]
		);
	}
}