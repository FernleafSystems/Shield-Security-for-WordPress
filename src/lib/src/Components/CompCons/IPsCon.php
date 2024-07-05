<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\BlacklistHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class IPsCon {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return self::con()->comps->opts_lookup->isPluginEnabled() && self::con()->db_con->ip_rules->isReady();
	}

	protected function run() {
		( new BlacklistHandler() )->execute();
		self::con()->comps->bot_signals->execute();
		self::con()->comps->crowdsec->execute();
	}
}