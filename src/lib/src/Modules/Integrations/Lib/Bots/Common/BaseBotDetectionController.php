<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;

abstract class BaseBotDetectionController extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return !$this->getCon()->this_req->request_bypasses_all_restrictions;
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
					/** @var BaseHandler $provider - it's actually FQ class string */
					return $provider::IsProviderInstalled();
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