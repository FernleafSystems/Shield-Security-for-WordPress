<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsRestApiRequestAuthenticated extends BaseRequestToRestAPI {

	/**
	 * @throws \Exception
	 */
	protected function execConditionCheck() :bool {
		return $this->req->rest_server->check_authentication() === true;
	}
}