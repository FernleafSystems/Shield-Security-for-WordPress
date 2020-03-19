<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function markReverted( EntryVO $oEntry ) {
		return $this->updateEntry( $oEntry, [
			'reverted_at' => Services::Request()->ts()
		] );
	}
}