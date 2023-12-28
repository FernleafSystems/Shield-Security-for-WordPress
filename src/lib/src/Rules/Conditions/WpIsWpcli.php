<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class WpIsWpcli extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'wp_is_wpcli';

	protected function execConditionCheck() :bool {
		return $this->req->wp_is_wpcli;
	}

	public function getDescription() :string {
		return __( 'Is the request triggered by WP-CLI.', 'wp-simple-firewall' );
	}
}