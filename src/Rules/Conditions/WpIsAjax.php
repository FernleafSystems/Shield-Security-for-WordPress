<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class WpIsAjax extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'wp_is_ajax';

	protected function execConditionCheck() :bool {
		return $this->req->wp_is_ajax;
	}

	public function getName() :string {
		return __( 'Is WP AJAX', 'wp-simple-firewall' );
	}

	public function getDescription() :string {
		return __( 'Is the request to the standard WordPress AJAX endpoint.', 'wp-simple-firewall' );
	}
}