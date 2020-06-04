<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

	use Common;

	/**
	 * @return int
	 */
	public function getLastReportId() {
		return $this->setColumnsToSelect( [ 'rid' ] )
					->setOrderBy( 'rid', 'DESC' )
					->setLimit( 1 )
					->queryVar();
	}
}