<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\UserMfa;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;

class ConfigPage extends BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'page_user_mfa_config';
	public const PRIMARY_MOD = 'login_protect';
	public const TEMPLATE = '/wpadmin_pages/my_login_security/index.twig';

	protected function getRenderData() :array {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		return [
			'content' => [
				'mfa_setup' => $mod->getMfaController()
								   ->getMfaProfilesCon()
								   ->renderUserProfileMFA()
			]
		];
	}
}