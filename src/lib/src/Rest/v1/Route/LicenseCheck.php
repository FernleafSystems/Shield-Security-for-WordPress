<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

class LicenseCheck extends LicenseBase {

	public const ROUTE_METHOD = \WP_REST_Server::ALLMETHODS;

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\LicenseCheck::class;
	}

	/**
	 * Allows Shield service to PING a site to perform a license check after customer activates license.
	 */
	protected function isShieldServiceAuthorised() :bool {
		return true;
	}
}