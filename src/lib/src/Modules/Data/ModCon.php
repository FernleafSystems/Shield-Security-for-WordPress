<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public const SLUG = 'data';

	public function getDbH_IPs() :DB\IPs\Ops\Handler {
		return self::con()->db_con ?
			self::con()->db_con->loadDbH( 'ips' ) : $this->getDbHandler()->loadDbH( 'ips' );
	}

	public function getDbH_UserMeta() :DB\UserMeta\Ops\Handler {
		return self::con()->db_con ?
			self::con()->db_con->loadDbH( 'user_meta' ) : $this->getDbHandler()->loadDbH( 'user_meta' );
	}

	public function getDbH_ReqLogs() :DB\ReqLogs\Ops\Handler {
		return self::con()->db_con ?
			self::con()->db_con->loadDbH( 'req_logs' ) : $this->getDbHandler()->loadDbH( 'req_logs' );
	}

	public function runDailyCron() {
		( new Lib\CleanDatabases() )->execute();
	}
}