<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\ModCon;

abstract class BaseBotDetectionController extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return $this->isEnabled() && !$this->getCon()->req->is_bypass_restrictions;
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
	public function getSelectedProviders() :array {
		return $this->getOptions()->getOpt( $this->getSelectedProvidersOptKey(), [] );
	}

	abstract public function getSelectedProvidersOptKey() :string;

	/**
	 * @return string[]
	 */
	public function enumProviders() :array {
		return [];
	}

	protected function isEnabled() :bool {
		return !empty( $this->getSelectedProviders() );
	}
}