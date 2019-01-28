<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render;

class IpBase extends Base {

	/**
	 * @param array $aItem
	 * @return string
	 */
	public function column_ip( $aItem ) {
		return $this->getIpWhoisLookupLink( $aItem[ 'ip' ] )
			   .$this->buildActions( [ $this->getActionButton_Delete( $aItem[ 'id' ] ) ] );
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'ip'             => 'IP Address',
			'label'          => 'Label',
			'transgressions' => 'Transgressions',
			'list'           => 'List',
			'last_access_at' => 'Last Access',
			'created_at'     => 'Date',
		);
	}
}