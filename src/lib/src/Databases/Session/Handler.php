<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;
use FernleafSystems\Wordpress\Services\Services;

class Handler extends Base\Handler {

	public function autoCleanDb() {
		$this->tableCleanExpired( 30 );
		// Remove soft-deleted sessions after 3 days
		$this->getQueryDeleter()
			 ->addWhereOlderThan( Services::Request()->carbon()->subDays( 5 )->timestamp, 'deleted_at' )
			 ->query();
	}
}