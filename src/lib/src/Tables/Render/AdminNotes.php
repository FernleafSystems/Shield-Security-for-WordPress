<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

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
		return array(
			'delete' => 'Delete',
		);
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'          => '&nbsp;',
			'note'        => 'Note',
			'wp_username' => 'Username',
			'created_at'  => 'Date',
		);
	}
}