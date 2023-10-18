<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\ReCaptcha;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

/**
 * @deprecated 18.5
 */
class TestRequest {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function test() :bool {
		return true;
	}

	/**
	 * @throws \Exception
	 */
	protected function runTest() :bool {
		return true;
	}
}