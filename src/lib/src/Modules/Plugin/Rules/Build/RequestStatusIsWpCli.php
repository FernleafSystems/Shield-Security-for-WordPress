<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\WpIsWpcli;

/**
 * @deprecated 18.6
 */
class RequestStatusIsWpCli extends RequestStatusBase {

	public const SLUG = 'shield/request_status_is_wpcli';

	protected function getName() :string {
		return 'Is WP-CLI?';
	}

	protected function getConditions() :array {
		return [
			'conditions' => WpIsWpcli::class,
		];
	}
}