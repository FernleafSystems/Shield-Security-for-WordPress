<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	Components,
	ModConsumer
};

class BlacklistHandler {

	use ExecOnce;
	use ModConsumer;
	use PluginCronsConsumer;

	protected function canRun() :bool {
		return self::con()->comps->opts_lookup->enabledIpAutoBlock() && self::con()->db_con->dbhIPRules()->isReady();
	}

	protected function run() {
		( new Components\UnblockIpByFlag() )->execute();
		( new ProcessOffenses() )->execute();
		$this->setupCronHooks();
	}

	public function runHourlyCron() {
		( new Components\ImportIpsFromFile() )->execute();
	}
}