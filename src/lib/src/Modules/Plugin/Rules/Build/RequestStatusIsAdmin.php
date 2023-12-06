<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rules\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\WpIsAdmin;

class RequestStatusIsAdmin extends RequestStatusBase {

	public const SLUG = 'shield/request_status_is_admin';

	protected function getName() :string {
		return 'Is Admin?';
	}

	protected function getConditions() :array {
		return [
			'conditions' => WpIsAdmin::class,
		];
	}
}