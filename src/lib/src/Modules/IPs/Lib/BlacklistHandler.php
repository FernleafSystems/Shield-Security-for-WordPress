<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\WildCardOptions;
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
		$this->setupCronHooks();

		( new IPs\Components\UnblockIpByFlag() )
			->setMod( $this->getMod() )
			->execute();
		( new ProcessOffenses() )
			->setMod( $this->getMod() )
			->execute();
		( new AutoUnblock() )
			->setMod( $this->getMod() )
			->execute();
	}

	public function runHourlyCron() {
		( new IPs\Components\ImportIpsFromFile() )
			->setMod( $this->getMod() )
			->execute();
	}

	/**
	 * @deprecated 15.0
	 */
	private function isRequestWhitelisted() :bool {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		$isWhitelisted = false;

		$whitelistPaths = array_map(
			function ( $value ) {
				return ( new WildCardOptions() )->buildFullRegexValue( $value, WildCardOptions::URL_PATH );
			},
			$this->getCon()->isPremiumActive() ? $opts->getOpt( 'request_whitelist', [] ) : []
		);

		if ( !empty( $whitelistPaths ) ) {
			$path = strtolower( '/'.ltrim( Services::Request()->getPath(), '/' ) );
			foreach ( $whitelistPaths as $rule ) {
				if ( preg_match( $rule, $path ) ) {
					$isWhitelisted = true;
					break;
				}
			}
		}
		return $isWhitelisted;
	}
}