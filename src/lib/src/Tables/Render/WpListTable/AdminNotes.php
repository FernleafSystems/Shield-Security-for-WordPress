<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

class AdminNotes extends Base {

	public function column_note( array $item ) :string {
		return esc_html( $item[ 'note' ] ).$this->buildActions( [ $this->getActionButton_Delete( $item[ 'id' ] ) ] );
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		return [
			'delete' => __( 'Delete', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			'cb'          => '&nbsp;',
			'note'        => __( 'Note', 'wp-simple-firewall' ),
			'wp_username' => __( 'Username' ),
			'created_at'  => __( 'Date' ),
		];
	}
}