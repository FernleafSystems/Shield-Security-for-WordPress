<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class WpIsWpcli extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'wp_is_wpcli';

	protected function execConditionCheck() :bool {
		return Services::WpGeneral()->isWpCli();
	}

	public function getDescription() :string {
		return __( 'Is the request triggered by WP-CLI.', 'wp-simple-firewall' );
	}

	protected function getPreviousResult() :?bool {
		return self::con()->this_req->wp_is_wpcli;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		self::con()->this_req->wp_is_wpcli = $result;
	}
}