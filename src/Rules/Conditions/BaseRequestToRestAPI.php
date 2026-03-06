<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;

abstract class BaseRequestToRestAPI extends Base {

	use Traits\TypeWordpress;

	public static function MinimumHook() :int {
		return WPHooksOrder::REST_API_INIT;
	}

	protected function getRestNamespace() :string {
		return explode( '/', $this->getRestRoute() )[ 0 ];
	}

	protected function getRestRoute() :string {
		return $this->req->getRestRoute();
	}
}
