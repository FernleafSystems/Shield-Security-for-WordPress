<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

abstract class MfaBase extends BaseAction {

	use SecurityAdminNotRequired;

	public const PRIMARY_MOD = 'login_protect';
}