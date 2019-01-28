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

		$aData = $this->getInsertData();
		if ( empty( $aData[ 'wp_username' ] ) ) {
			$sUser = Services::WpUsers()->getCurrentWpUsername();
			$aData[ 'wp_username' ] = empty( $sUser ) ? 'unknown' : $sUser;
		}

		return $this->setInsertData( $aData );
	}

	/**
	 * @param string $sNote
	 * @return bool
	 */
	public function create( $sNote ) {
		return $this->setInsertData( array( 'note' => esc_sql( $sNote ) ) )->query() === 1;
	}
}