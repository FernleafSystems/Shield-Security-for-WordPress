<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\ModConsumer;

abstract class BaseBotDetectionController {

	use ExecOnce;
	use ModConsumer;

	private $installedProviders;

	protected function canRun() :bool {
		return !$this->con()->this_req->request_bypasses_all_restrictions;
	}

	/**
	 * @return BaseHandler[]|string[]
	 */
	public function getInstalled() :array {
		if ( !isset( $this->installedProviders ) ) {
			$this->installedProviders = \array_filter(
				$this->enumProviders(),
				function ( string $provider ) {
					return $provider::IsProviderAvailable();
				}
			);
		}
		return $this->installedProviders;
	}

	protected function run() {
		\array_map(
			function ( string $providerClass ) {
				( new $providerClass() )->execute();
			},
			\array_intersect_key( $this->getInstalled(), \array_flip( $this->getSelectedProviders() ) )
		);
	}

	/**
	 * @return string[]
	 */
	public function getSelectedProviders() :array {
		return $this->opts()->getOpt( $this->getSelectedProvidersOptKey(), [] );
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