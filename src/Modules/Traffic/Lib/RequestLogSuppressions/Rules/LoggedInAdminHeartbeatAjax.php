<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions\Rules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogSuppressions\{
	BaseRule,
	Context
};

class LoggedInAdminHeartbeatAjax extends BaseRule {

	public function matches( Context $context ) :bool {
		$screenId = $context->screenId();

		return $context->isLoggedIn()
			   && $context->method() === 'POST'
			   && $context->isAjax()
			   && $context->path() === '/wp-admin/admin-ajax.php'
			   && $context->requestAction() === 'heartbeat'
			   && $screenId !== ''
			   && $screenId !== 'front';
	}
}
