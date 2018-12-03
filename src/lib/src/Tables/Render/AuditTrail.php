<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class AuditTrail extends Base {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_actions( $aItem ) {
		$sContent = '';
		if ( isset( $aItem[ 'meta' ][ 'param' ] ) ) {
			$sContent = $this->getActionButton_AddParam( $aItem[ 'id' ] );
		}

		return $sContent;
	}

	/**
	 * @param int $nId
	 * @return string
	 */
	protected function getActionButton_AddParam( $nId ) {
		return sprintf( '<button title="%s"'.
						' class="btn btn-sm btn-outline-warning action custom-action"' .
						' data-rid="%s" data-custom-action="%s">'.
						'<span class="dashicons dashicons-plus-alt"></span></button>',
			_wpsf__( 'Add Parameter To Whitelist' ), $nId, 'item_addparamwhite' );
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
	public function get_columns() {
		return array(
			'details'    => 'Details',
			'message'    => 'Message',
			'event'      => 'Event',
			'created_at' => 'Date',
			'actions'    => $this->getColumnHeader_Actions(),
		);
	}
}