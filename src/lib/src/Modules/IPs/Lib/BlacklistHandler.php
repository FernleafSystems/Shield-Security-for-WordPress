<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;

class BlacklistHandler extends Modules\Base\Common\ExecOnceModConsumer {

	use ExecOnce;
	use IPs\ModConsumer;
	use PluginCronsConsumer;

	protected function canRun() :bool {
		return $this->opts()->isEnabledAutoBlackList() || $this->opts()->isEnabledCrowdSecAutoBlock();
	}

	protected function run() {
		( new IPs\Components\UnblockIpByFlag() )->execute();
		( new ProcessOffenses() )
			->setMod( $this->getMod() )
			->execute();
		$this->setupCronHooks();
	}

	public function runHourlyCron() {
		( new IPs\Components\ImportIpsFromFile() )
			->setMod( $this->getMod() )
			->execute();
	}
}