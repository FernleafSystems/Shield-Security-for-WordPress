<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\{
	AutoUnblockVisitor,
	AutoUnblockMagicLink
};

class BlacklistHandler extends Modules\Base\Common\ExecOnceModConsumer {

	use PluginCronsConsumer;

	protected function canRun() :bool {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledAutoBlackList() || $opts->isEnabledCrowdSecAutoBlock();
	}

	protected function run() {
		( new IPs\Components\UnblockIpByFlag() )
			->setMod( $this->getMod() )
			->execute();
		( new ProcessOffenses() )
			->setMod( $this->getMod() )
			->execute();
		( new AutoUnblockVisitor() )
			->setMod( $this->getMod() )
			->execute();
		( new AutoUnblockMagicLink() )
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