<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request\Options;

class GetAll extends BaseBulk {

	protected function getRequestProcessorClass() :string {
		return Options\GetAll::class;
	}
}