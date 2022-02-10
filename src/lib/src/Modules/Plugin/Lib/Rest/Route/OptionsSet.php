<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Rest\Request;

class OptionsSet extends OptionsBase {

	public function getArgMethods() :array {
		return [ \WP_REST_Server::EDITABLE ];
	}

	protected function getRequestProcessorClass() :string {
		return Request\OptionsSet::class;
	}
}