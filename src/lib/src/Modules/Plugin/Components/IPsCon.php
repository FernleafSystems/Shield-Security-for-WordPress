<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\BlacklistHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;

class IPsCon {

	use ExecOnce;
	use ModConsumer;

	protected function canRun() :bool {
		return $this->opts()->isOpt( 'enable_ips', 'Y' ) && self::con()->db_con->dbhIPRules()->isReady();
	}

	protected function run() {
		( new BlacklistHandler() )->execute();
		$this->mod()->getBotSignalsController()->execute();
		$this->mod()->getCrowdSecCon()->execute();
	}
}