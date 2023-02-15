<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class WpIsPermalinksEnabled extends Base {

	public const SLUG = 'wp_is_permalinks_enabled';

	protected function execConditionCheck() :bool {
		return Services::WpGeneral()->isPermalinksEnabled();
	}
}