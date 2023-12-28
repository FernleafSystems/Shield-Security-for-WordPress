<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class WpIsAjax extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'wp_is_ajax';

	protected function execConditionCheck() :bool {
		return Services::WpGeneral()->isAjax();
	}

	public function getDescription() :string {
		return __( 'Is the request to the standard WordPress AJAX endpoint.', 'wp-simple-firewall' );
	}

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->wp_is_ajax;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->wp_is_ajax = $result;
	}
}