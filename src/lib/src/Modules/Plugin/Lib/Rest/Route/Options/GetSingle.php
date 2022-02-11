<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request\Options;

class GetSingle extends BaseSingle {

	protected function getRequestProcessorClass() :string {
		return Options\GetSingle::class;
	}
}