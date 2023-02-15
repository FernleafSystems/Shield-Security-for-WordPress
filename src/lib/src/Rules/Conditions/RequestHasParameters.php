<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class RequestHasParameters extends Base {

	public const SLUG = 'request_has_parameters';

	protected function execConditionCheck() :bool {
		$req = Services::Request();
		return !empty( $req->query ) || ( $req->isPost() && !empty( $req->post ) );
	}
}