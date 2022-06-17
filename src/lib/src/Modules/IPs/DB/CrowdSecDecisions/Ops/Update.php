<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecDecisions\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	use Common;

	public function updateLastAccessAt() :bool {
		return $this->setUpdateData( [ 'last_access_at' => Services::Request()->ts() ] )
					->query();
	}
}