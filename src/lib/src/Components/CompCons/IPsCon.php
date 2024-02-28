<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\BlacklistHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class IPsCon {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return self::con()->opts->optIs( 'enable_ips', 'Y' ) && self::con()->db_con->dbhIPRules()->isReady();
	}

	protected function run() {
		( new BlacklistHandler() )->execute();
		self::con()->comps->bot_signals->execute();
		self::con()->comps->crowdsec->execute();
	}
}