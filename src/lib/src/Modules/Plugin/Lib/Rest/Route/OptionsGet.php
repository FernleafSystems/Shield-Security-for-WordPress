<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request;

class OptionsGet extends OptionsBase {

	protected function getRequestProcessorClass() :string {
		return Request\OptionsGet::class;
	}
}