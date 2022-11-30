<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\FullPage\Mfa;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

abstract class BaseLoginIntentPage extends Actions\Render\FullPage\BaseFullPageRender {

	use Actions\Traits\AuthNotRequired;

	public const PRIMARY_MOD = 'login_protect';
	public const LOGIN_INTENT_PAGE_SHIELD = '';
}