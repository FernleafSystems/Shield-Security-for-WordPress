<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

abstract class SecurityAdminBase extends BaseAction {

	use Actions\Traits\SecurityAdminNotRequired;

	protected function getDefaults() :array {
		return [
			'primary_mod_slug' => 'admin_access_restriction',
		];
	}
}