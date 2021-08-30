<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\Traits\Select_IPTable;

class Select extends Base\Select {

	use Common;
	use Select_IPTable;
}