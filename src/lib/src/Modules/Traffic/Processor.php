<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;

class Processor extends Modules\BaseShield\Processor {

	use ModConsumer;

	protected function run() {
		$this->con()
			 ->getModule_Traffic()
			 ->getRequestLogger()
			 ->execute();
	}
}