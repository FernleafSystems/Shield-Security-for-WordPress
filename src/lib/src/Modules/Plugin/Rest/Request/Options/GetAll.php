<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request\Options;

class GetAll extends Base {

	/**
	 * @inheritDoc
	 */
	protected function process() :array {
		return $this->getAllOptions();
	}
}