<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\ModCon;

abstract class BaseBotDetectionController extends ExecOnceModConsumer {

	protected function canRun() :bool {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $this->isEnabled() && !$mod->isVisitorWhitelisted();
	}

	protected function run() {
		array_map(
			function ( $providerClass ) {
				( new $providerClass() )->setMod( $this->getMod() )->execute();
			},
			array_filter(
				array_intersect_key(
					$this->enumProviders(),
					array_flip( $this->getSelectedProviders() )
				),
				function ( $provider ) {
					return call_user_func( $provider.'::IsProviderInstalled' );
				}
			)
		);
	}

	/**
	 * @return string[]
	 */
	abstract public function getSelectedProviders() :array;

	/**
	 * @return string[]
	 */
	public function enumProviders() :array {
		return [];
	}

	protected function isEnabled() :bool {
		return false;
	}
}