<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\WpIsWpcli;

class RequestStatusIsWpCli extends RequestStatusBase {

	public const SLUG = 'shield/request_status_is_wpcli';

	protected function getName() :string {
		return 'Is WP-CLI?';
	}

	protected function getConditions() :array {
		return [
			'logic' => static::LOGIC_OR,
			'conditions' => [
				[
					'conditions' => WpIsWpcli::class,
				],
			]
		];
	}
}