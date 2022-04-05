<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpListTable;

class Sessions extends Base {

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_cb( $item ) {
		return empty( $item[ 'shield_unique' ] ) ? ''
			: sprintf( '<input type="checkbox" name="ids" value="%s-%s" />', $item[ 'user_id' ], $item[ 'shield_unique' ] );
	}

	/**
	 * @param array $item
	 * @return string
	 */
	public function column_details( $item ) {
		$actions = [];
		if ( !empty( $item[ 'shield_unique' ] ) ) {
			$actions[] = $this->getActionButton_Delete(
				sprintf( '%s-%s', $item[ 'user_id' ], $item[ 'shield_unique' ] ),
				__( 'Discard Session', 'wp-simple-firewall' )
			);
		}
		return sprintf( '%s<br />%s%s',
			$item[ 'wp_username' ],
			$item[ 'ip' ],
			$this->buildActions( $actions )
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