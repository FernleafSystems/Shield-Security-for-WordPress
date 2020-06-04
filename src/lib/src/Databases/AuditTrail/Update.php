<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	/**
	 * @param EntryVO $oEntry
	 * @param int     $nIncrease
	 * @return bool
	 */
	public function updateCount( $oEntry, $nIncrease = 1 ) {
		return $this->updateEntry( $oEntry, [
			'count'      => $oEntry->count + $nIncrease,
			'updated_at' => Services::Request()->ts()
		] );
	}
}