<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AdminNotes;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseInsert;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends BaseInsert {

	/**
	 * @param string $sNote
	 * @return bool
	 */
	public function create( $sNote ) {
		$oUser = Services::WpUsers()->getCurrentWpUser();
		$aData = array(
			'wp_username' => ( $oUser instanceof \WP_User ) ? $oUser->user_login : 'unknown',
			'note'        => esc_sql( $sNote ),
		);
		return $this->setInsertData( $aData )->query() === 1;
	}
}