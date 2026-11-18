<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

/**
 * The PIN dialog that unlocks Security Admin from WP admin pages outside Shield, held in a <template> until opened.
 */
class FormSecurityAdminLoginBox extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use SecurityAdminNotRequired;

	public const SLUG = 'render_form_security_admin_loginbox';
	public const TEMPLATE = '/components/security_admin/login_box.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'title'   => __( 'Security Admin Login', 'wp-simple-firewall' ),
				'message' => __( 'Enter your Security Admin PIN', 'wp-simple-firewall' ),
				'unlock'  => __( 'Unlock', 'wp-simple-firewall' ),
			],
		];
	}
}
