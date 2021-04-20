<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class BaseBotDetectionController extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return $this->isEnabled();
	}

	protected function run() {
		array_map(
			function ( $provider ) {
				$provider->execute();
			},
			$this->getInstalledProviders()
		);
	}

	/**
	 * Inserts the ModCon;
	 * @return BaseHandler[]
	 */
	public function getInstalledProviders() :array {
		return array_map(
			function ( $provider ) {
				return $provider->setMod( $this->getMod() );
			},
			array_filter(
				$this->enumProviders(),
				function ( $provider ) {
					return $provider::IsProviderInstalled();
				}
			)
		);
	}

	/**
	 * @return BaseHandler[]
	 */
	public function enumProviders() :array {
		return [];
	}

	protected function isEnabled() :bool {
		return false;
	}
}