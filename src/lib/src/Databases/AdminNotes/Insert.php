<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends Base\Insert {

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function verifyInsertData() {
		parent::verifyInsertData();

		$data = $this->getInsertData();
		if ( empty( $data[ 'wp_username' ] ) ) {
			$username = Services::WpUsers()->getCurrentWpUsername();
			$data[ 'wp_username' ] = empty( $username ) ? 'unknown' : $username;
		}

		return $this->setInsertData( $data );
	}

	public function create( string $note ) :bool {
		return $this->setInsertData( [ 'note' => esc_sql( $note ) ] )->query() === 1;
	}
}