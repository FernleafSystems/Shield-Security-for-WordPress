<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\UserMfa;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class UserMfaBase extends BaseRender {

	/** Secadmin is handled later on */
	use SecurityAdminNotRequired;

	const PRIMARY_MOD = 'login_protect';
}