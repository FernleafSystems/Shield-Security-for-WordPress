<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Rest\Route;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Rest\Request;

class LicenseCheck extends Base {

	const ROUTE_METHOD = \WP_REST_Server::EDITABLE;

	protected function getRequestProcessorClass() :string {
		return Request\LicenseCheck::class;
	}
}