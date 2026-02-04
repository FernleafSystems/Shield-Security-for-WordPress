<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\WPHooksOrder;

class IsWpSearch extends Base {

	use Traits\TypeWordpress;

	public static function MinimumHook() :int {
		return WPHooksOrder::TEMPLATE_REDIRECT;
	}

	protected function execConditionCheck() :bool {
		return is_search();
	}

	public function getName() :string {
		return __( 'Is WP Search', 'wp-simple-firewall' );
	}

	public function getDescription() :string {
		return __( 'Is WordPress Search Request.', 'wp-simple-firewall' );
	}
}