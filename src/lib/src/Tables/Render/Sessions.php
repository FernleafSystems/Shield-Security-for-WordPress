<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class Sessions extends Base {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_details( $aItem ) {
		return sprintf( '%s<br />%s%s%s',
			$aItem[ 'wp_username' ],
			$this->getIpWhoisLookupLink( $aItem[ 'ip' ] ),
			$aItem[ 'your_ip' ],
			$this->buildActions( $this->getActionButton_Delete( $aItem[ 'id' ] ) )
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
			'last_activity_at' => 'Last Activity',
			'logged_in_at'     => 'Logged In',
		);
	}
}