<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class ScanAggregate extends ScanBase {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_path( $aItem ) {

		$sContent = parent::column_path( $aItem );

		if ( !empty( $aItem[ 'actions' ] ) ) {
			$sContent .= $this->buildActions(
				array_map(
					function ( $aActionDef ) {
						return $this->buildActionButton_CustomArray( $aActionDef );
					},
					$aItem[ 'actions' ]
				)
			);
		}

		return $sContent;
	}

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_status( $aItem ) {
		$sStatus = sprintf( '<strong>%s</strong>', $aItem[ 'status' ] );
		if ( !empty( $aItem[ 'explanation' ] ) ) {
			$sStatus .= '<ul><li>'.implode( '</li><li>', $aItem[ 'explanation' ] ).'</li></ul>';
		}
		return $sStatus;
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