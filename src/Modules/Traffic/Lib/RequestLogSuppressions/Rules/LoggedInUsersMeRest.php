<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions\{
	BaseRule,
	Context
};

class LoggedInUsersMeRest extends BaseRule {

	public function matches( Context $context ) :bool {
		return $context->isLoggedIn()
			   && $context->method() === 'GET'
			   && $context->restRoute() === 'wp/v2/users/me';
	}
}
