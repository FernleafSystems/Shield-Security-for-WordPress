<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class WpIsAdmin extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'wp_is_admin';

	protected function execConditionCheck() :bool {
		return $this->req->wp_is_admin;
	}

	public function getName() :string {
		return __( 'Is WP Admin', 'wp-simple-firewall' );
	}

	public function getDescription() :string {
		return __( 'Is the request to the WordPress admin area.', 'wp-simple-firewall' );
	}
}