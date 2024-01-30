<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class WpIsPermalinksEnabled extends Base {

	use Traits\TypeWordpress;

	public const SLUG = 'wp_is_permalinks_enabled';

	protected function execConditionCheck() :bool {
		return $this->req->wp_is_permalinks_enabled;
	}

	public function getName() :string {
		return __( 'Is WP Permalinks Enabled', 'wp-simple-firewall' );
	}

	public function getDescription() :string {
		return __( 'Are WordPress permalinks enabled.', 'wp-simple-firewall' );
	}
}