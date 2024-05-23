<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class FormSecurityAdminLoginBox extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_form_security_admin_loginbox';
	public const TEMPLATE = '/components/security_admin/login_box.twig';

	protected function getRenderData() :array {
		$opts = self::con()->opts;
		return [
			'flags'   => [
				'restrict_options' => \method_exists( $opts, 'optIs' ) ? $opts->optIs( 'admin_access_restrict_options', 'Y' )
					: self::con()->getModule_SecAdmin()->opts()->isOpt( 'admin_access_restrict_options', 'Y' ),
			],
			'strings' => [
				'access_message' => __( 'Enter your Security Admin PIN', 'wp-simple-firewall' ),
			],
		];
	}
}