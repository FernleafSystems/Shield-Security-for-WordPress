<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanAggregate extends ScanBase {

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_path( $item ) {

		$sContent = parent::column_path( $item );

		if ( !empty( $item[ 'actions' ] ) ) {
			$sContent .= $this->buildActions(
				array_map(
					function ( $aActionDef ) {
						return $this->buildActionButton_CustomArray( $aActionDef );
					},
					$item[ 'actions' ]
				)
			);
		}

		return $sContent;
	}

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_status( $item ) {
		$status = sprintf( '<strong>%s</strong>', $item[ 'status' ] );
		if ( !empty( $item[ 'explanation' ] ) ) {
			$status .= '<ul><li>'.implode( '</li><li>', $item[ 'explanation' ] ).'</li></ul>';
		}
		return $status;
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		return [
			'repair' => __( 'Repair', 'wp-simple-firewall' ),
			'ignore' => __( 'Ignore', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array_merge(
			[ 'cb' => '&nbsp;' ],
			parent::get_columns()
		);
	}

	/**
	 * override this in order to display a custom row
	 * @param array $aItem
	 */
	public function single_row_custom( $aItem ) {
		$sRowContent = sprintf( '%s: %s', __( 'Scan Area', 'wp-simple-firewall' ), $aItem[ 'title' ] );
		echo sprintf( '<tr class="row-sticky"><td colspan=%s><h5>%s</h5></td></tr>',
			count( $this->get_columns() ),
			$sRowContent
		);
	}
}