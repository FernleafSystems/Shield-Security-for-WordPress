<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Insert extends Base\Insert {

	/**
	 * @param int    $nReportId
	 * @param string $sType
	 * @return bool
	 */
	public function create( $nReportId, $sType ) {
		return $this->setInsertData( [
				'rid'     => $nReportId,
				'type'    => $sType,
				'sent_at' => Services::Request()->ts()
			] )->query() === 1;
	}
}