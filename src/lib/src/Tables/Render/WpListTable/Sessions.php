<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

class Sessions extends Base {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_details( $aItem ) {
		return sprintf( '%s<br />%s%s',
			$aItem[ 'wp_username' ],
			$aItem[ 'ip' ],
			$this->buildActions( [
				$this->getActionButton_Delete( $aItem[ 'id' ],
					__( 'Discard Session', 'wp-simple-firewall' ) )
			] )
		);
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		return [
			'delete' => __( 'Discard', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return [
			'cb'               => '&nbsp;',
			'details'          => __( 'Details', 'wp-simple-firewall' ),
			'is_secadmin'      => __( 'Security Admin', 'wp-simple-firewall' ),
			'last_activity_at' => __( 'Last Activity At', 'wp-simple-firewall' ),
			'logged_in_at'     => __( 'Logged-In', 'wp-simple-firewall' ),
		];
	}
}