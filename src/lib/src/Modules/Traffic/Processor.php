<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Processor {

	use ModConsumer;

	protected function run() {
		self::con()
			->getModule_Traffic()
			->getRequestLogger()
			->execute();
	}
}