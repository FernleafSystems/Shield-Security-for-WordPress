<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Comments;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseDelete;

class Delete extends BaseDelete {

	/**
	 * @param EntryVO $oToken
	 * @return bool
	 */
	public function deleteToken( $oToken ) {
		return $this->deleteEntry( $oToken );
	}

	/**
	 * @return Select
	 */
	protected function getSelector() {
		return ( new Select() )->setTable( $this->getTable() );
	}
}