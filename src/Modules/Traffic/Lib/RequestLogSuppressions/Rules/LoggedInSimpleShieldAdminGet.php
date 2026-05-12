<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions\{
	BaseRule,
	Context
};

class LoggedInSimpleShieldAdminGet extends BaseRule {

	private const ROUTE_QUERY_KEYS = [
		'page',
		PluginNavs::FIELD_NAV,
		PluginNavs::FIELD_SUBNAV,
	];

	public function matches( Context $context ) :bool {
		return $context->isLoggedIn()
			   && $context->isSecurityAdmin()
			   && $context->method() === 'GET'
			   && !$context->isAjax()
			   && $context->isAdmin()
			   && $context->scriptName() === 'admin.php'
			   && $context->isPluginAdminPage()
			   && PluginNavs::NavExists( $context->nav(), $context->subNav() )
			   && $context->queryKeys() === $this->requiredQueryKeys();
	}

	private function requiredQueryKeys() :array {
		$keys = self::ROUTE_QUERY_KEYS;
		\sort( $keys );
		return $keys;
	}
}
