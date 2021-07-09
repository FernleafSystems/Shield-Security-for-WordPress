<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class BlacklistHandler extends Modules\Base\Common\ExecOnceModConsumer {

	use PluginCronsConsumer;

	protected function canRun() :bool {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledAutoBlackList();
	}

	protected function run() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();

		if ( $this->getCon()->isPremiumActive() ) {
			$this->setupCronHooks();
		}

		( new IPs\Components\UnblockIpByFlag() )
			->setMod( $mod )
			->run();

		if ( !$mod->isVisitorWhitelisted() && !$this->isRequestWhitelisted() ) {

			// We setup offenses processing immediately but run the blocks on 'init'
			( new ProcessOffenses() )
				->setMod( $this->getMod() )
				->execute();

			add_action( 'init', function () {
				( new BlockRequest() )
					->setMod( $this->getMod() )
					->execute();
			}, -100000 );
		}
	}

	private function isRequestWhitelisted() :bool {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		$isWhitelisted = false;
		$whitelistPaths = $opts->getRequestWhitelistAsRegex();
		if ( !empty( $whitelistPaths ) ) {
			$sPath = strtolower( '/'.ltrim( (string)Services::Request()->getPath(), '/' ) );
			foreach ( $whitelistPaths as $rule ) {
				if ( preg_match( $rule, $sPath ) ) {
					$isWhitelisted = true;
					break;
				}
			}
		}
		return $isWhitelisted;
	}

	public function runHourlyCron() {
		( new IPs\Components\ImportIpsFromFile() )
			->setMod( $this->getMod() )
			->execute();
	}
}