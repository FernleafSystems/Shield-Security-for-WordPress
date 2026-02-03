<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class WpIsDebug extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'wp_is_wpcli';

	protected function execConditionCheck() :bool {
		return $this->req->wp_is_debug;
	}

	public function getName() :string {
		return __( 'Is WP Debug Active', 'wp-simple-firewall' );
	}

	public function getDescription() :string {
		return __( 'Is WordPress set to debug mode via WP_DEBUG constant.', 'wp-simple-firewall' );
	}
}