<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class AdminNotesTable extends ICWP_BaseTable {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_actions( $aItem ) {
		return $this->getActionButton_Delete( $aItem[ 'id' ] );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'note'        => 'Note',
			'wp_username' => 'Username',
			'created_at'  => 'Date',
			'actions'     => $this->getColumnHeader_Actions(),
		);
	}
}