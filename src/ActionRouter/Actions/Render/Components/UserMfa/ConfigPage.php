<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\UserMfa;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AnyUserAuthRequired;

class ConfigPage extends BaseRender {

	use AnyUserAuthRequired;

	public const SLUG = 'page_user_mfa_config';
	public const TEMPLATE = '/wpadmin_pages/my_login_security/index.twig';

	protected function getRenderData() :array {
		return [
			'content' => [
				'mfa_setup' => self::con()->comps->mfa->getMfaProfilesCon()->renderUserProfileMFA()
			]
		];
	}
}