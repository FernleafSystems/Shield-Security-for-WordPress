<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Options;

class FormSecurityAdminLoginBox extends BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_form_security_admin_loginbox';
	public const TEMPLATE = '/components/security_admin/login_box.twig';

	protected function getRenderData() :array {
		/** @var Options $opts */
		$opts = self::con()->getModule_SecAdmin()->opts();
		return [
			'flags'   => [
				'restrict_options' => $opts->isRestrictWpOptions()
			],
			'strings' => [
				'access_message' => __( 'Enter your Security Admin PIN', 'wp-simple-firewall' ),
			],
		];
	}
}