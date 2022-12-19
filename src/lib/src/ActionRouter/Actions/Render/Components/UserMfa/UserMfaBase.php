<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\UserMfa;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class UserMfaBase extends BaseRender {

	/** Secadmin is handled later on */
	use SecurityAdminNotRequired;

	public const PRIMARY_MOD = 'login_protect';
}