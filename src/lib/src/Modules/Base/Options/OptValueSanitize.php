<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

/**
 * @deprecated 19.1
 */
class OptValueSanitize {

	use ModConsumer;

	/**
	 * @param mixed $value
	 * @return mixed
	 * @throws \Exception
	 */
	public function run( string $key, $value ) {
		return $value;
	}
}