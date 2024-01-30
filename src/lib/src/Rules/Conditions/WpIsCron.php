<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class WpIsCron extends Base {

	use Traits\TypeWordpress;

	protected function execConditionCheck() :bool {
		return $this->req->wp_is_cron;
	}

	public function getName() :string {
		return __( 'Is WP CRON', 'wp-simple-firewall' );
	}

	public function getDescription() :string {
		return __( 'Is the request to the WordPress cron.', 'wp-simple-firewall' );
	}
}