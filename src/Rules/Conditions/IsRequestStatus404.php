<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;

class IsRequestStatus404 extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'is_request_status_404';

	public static function MinimumHook() :int {
		return WPHooksOrder::TEMPLATE_REDIRECT;
	}

	protected function execConditionCheck() :bool {
		$this->addConditionTriggerMeta( 'path', $this->req->path );
		return is_404();
	}

	public function getName() :string {
		return __( 'Is Request Status 404', 'wp-simple-firewall' );
	}

	public function getDescription() :string {
		return __( 'Is the request an HTTP 404.', 'wp-simple-firewall' );
	}
}