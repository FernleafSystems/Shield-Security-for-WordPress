<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

class Get extends Base {

	/**
	 * @inheritDoc
	 */
	protected function process() :array {
		return $this->getAllOptions();
	}
}