<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class BaseBotDetectionController {

	use ModConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		return $this->isEnabled();
	}

	protected function run() {
		array_map(
			function ( $provider ) {
				$provider->setMod( $this->getMod() )->execute();
			},
			array_filter(
				$this->enumProviders(),
				function ( $provider ) {
					return $provider::IsHandlerAvailable();
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