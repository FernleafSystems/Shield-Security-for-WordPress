<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;

abstract class BaseBotDetectionController extends ExecOnceModConsumer {

	private $installedProviders;

	protected function canRun() :bool {
		return !$this->getCon()->this_req->request_bypasses_all_restrictions;
	}

	/**
	 * @return BaseHandler[]|string[]
	 */
	public function getInstalled() :array {
		if ( !isset( $this->installedProviders ) ) {
			$this->installedProviders = array_filter(
				$this->enumProviders(),
				function ( string $provider ) {
					return $provider::IsProviderInstalled();
				}
			);
		}
		return $this->installedProviders;
	}

	protected function run() {
		array_map(
			function ( string $providerClass ) {
				( new $providerClass() )->setMod( $this->getMod() )->execute();
			},
			array_intersect_key( $this->getInstalled(), array_flip( $this->getSelectedProviders() ) )
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
	 * @return BaseHandler[]|string[]
	 */
	public function enumProviders() :array {
		return [];
	}

	protected function isEnabled() :bool {
		return !empty( $this->getSelectedProviders() );
	}
}