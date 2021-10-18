<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ResultItems\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	public function setItemDeleted( int $recordID ) :bool {
		return $this->updateById( $recordID, [ 'item_deleted_at' => Services::Request()->ts() ] );
	}

	public function setItemRepaired( int $recordID ) :bool {
		return $this->updateById( $recordID, [ 'item_repaired_at' => Services::Request()->ts() ] );
	}
}