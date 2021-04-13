<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\UserForms;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class UserFormsController {

	use ModConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		return $this->isEnabled();
	}

	protected function run() {
		foreach ( $this->enumProviders() as $provider ) {
			$provider->setMod( $this->getMod() )->execute();
		}
	}

	private function isEnabled() :bool {
		return !empty( $this->getOptions()->getOpt( 'user_form_providers' ) );
	}

	/**
	 * @return Handlers\Base[]
	 */
	private function enumProviders() :array {
		return [
			new Handlers\LifterLMS(),
		];
	}
}