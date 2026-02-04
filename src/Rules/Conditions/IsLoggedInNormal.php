<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;
use FernleafSystems\Wordpress\Services\Services;

class IsLoggedInNormal extends Base {

	use Traits\TypeUser;

	public const SLUG = 'is_logged_in_normal';

	public function getDescription() :string {
		return __( 'Is the request from a logged-in user.', 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$matched = Services::WpUsers()->isUserLoggedIn();
		if ( $matched && !isset( $this->req->session ) ) {
			$this->req->session = self::con()->comps->session->current();
		}
		return $matched;
	}

	public static function MinimumHook() :int {
		return WPHooksOrder::INIT;
	}
}