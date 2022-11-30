<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

abstract class SecurityAdminBase extends BaseAction {

	use Actions\Traits\SecurityAdminNotRequired;

	public const PRIMARY_MOD = 'admin_access_restriction';
}