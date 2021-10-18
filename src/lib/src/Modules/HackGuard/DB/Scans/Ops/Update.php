<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\Scans\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	public function setFinished( Record $record ) :bool {
		return $this->updateRecord( $record, [ 'finished_at' => Services::Request()->ts() ] );
	}
}