<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class Sessions extends Base {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_actions( $aItem ) {
		return $this->getActionButton_Delete( $aItem[ 'id' ] );
	}

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_details( $aItem ) {
		return sprintf( '%s<br />%s%s',
			$aItem[ 'wp_username' ],
			$this->getIpWhoisLookupLink( $aItem[ 'ip' ] ),
			$aItem[ 'your_ip' ]
		);
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
			'cb'               => '&nbsp;',
			'details'          => 'Details',
			'is_secadmin'      => 'Security Admin',
			'logged_in_at'     => 'Logged In',
			'last_activity_at' => 'Last Activity',
			'actions'          => $this->getColumnHeader_Actions(),
		);
	}
}