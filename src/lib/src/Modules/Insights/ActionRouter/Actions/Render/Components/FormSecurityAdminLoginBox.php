<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\SecurityAdminLogin;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;

class FormSecurityAdminLoginBox extends BaseRender {

	use SecurityAdminNotRequired;

	const PRIMARY_MOD = 'admin_access_restriction';
	const SLUG = 'render_form_security_admin_loginbox';
	const TEMPLATE = '/components/security_admin/login_box.twig';

	protected function getRenderData() :array {
		/** @var Options $opts */
		$opts = $this->primary_mod->getOptions();
		return [
			'flags'   => [
				'restrict_options' => $opts->isRestrictWpOptions()
			],
			'strings' => [
				'access_message' => __( 'Enter your Security Admin PIN', 'wp-simple-firewall' ),
			],
			'ajax'    => [
				'sec_admin_login' => ActionData::BuildJson( SecurityAdminLogin::SLUG ),
			]
		];
	}
}