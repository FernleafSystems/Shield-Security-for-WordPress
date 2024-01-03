<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\ModConsumer;

/**
 * @deprecated 18.6
 */
class IsRequestLogged {

	use ModConsumer;

	private static $excluded = null;

	public function isLogged() :bool {
		return !self::con()->plugin_deleting && apply_filters( 'shield/is_log_traffic', false );
	}

	private function isExcluded() :bool {
		return false;
	}

	private function isRequestTypeExcluded() :bool {
		return false;
	}

	private function isCustomExcluded() :bool {
		return false;
	}
}