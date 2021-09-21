<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

class AuditTrail extends Base {

	/**
	 * Generates content for a single row of the table
	 *
	 * @param object $item The current item
	 * @since 3.1.0
	 *
	 */
	public function single_row( $item ) {
		echo sprintf( '<tr class="audit-cat-%s">', $item[ 'category' ] );
		$this->single_row_columns( $item );
		echo '</tr>';
	}

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

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_user( $item ) {
		return $item[ 'wp_username' ];
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			'user'       => __( 'User' ),
			'ip'         => __( 'IP' ),
			'message'    => __( 'Message', 'wp-simple-firewall' ),
			'created_at' => __( 'Date' ),
		];
	}
}