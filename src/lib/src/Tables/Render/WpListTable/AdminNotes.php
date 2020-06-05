<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

class AdminNotes extends Base {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_note( $aItem ) {
		return $aItem[ 'note' ].$this->buildActions( $this->getActionButton_Delete( $aItem[ 'id' ] ) );
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