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
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();

		if ( $this->getCon()->isPremiumActive() ) {
			$this->setupCronHooks();
		}

		( new IPs\Components\UnblockIpByFlag() )
			->setMod( $mod )
			->run();

		if ( !$this->getCon()->this_req->is_bypass_restrictions ) {

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

	public function runHourlyCron() {
		( new IPs\Components\ImportIpsFromFile() )
			->setMod( $this->getMod() )
			->execute();
	}
}