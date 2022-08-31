<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	AuditTrail,
	Traffic
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public function getDbH_IPs() :DB\IPs\Ops\Handler {
		return $this->getDbHandler()->loadDbH( 'ips' );
	}

	public function getDbH_UserMeta() :DB\UserMeta\Ops\Handler {
		return $this->getDbHandler()->loadDbH( 'user_meta' );
	}

	public function getDbH_ReqLogs() :DB\ReqLogs\Ops\Handler {
		$this->getDbH_IPs();
		return $this->getDbHandler()->loadDbH( 'req_logs' );
	}

	protected function cleanupDatabases() {
		( new Lib\CleanDatabases() )
			->setMod( $this )
			->execute();
	}
}