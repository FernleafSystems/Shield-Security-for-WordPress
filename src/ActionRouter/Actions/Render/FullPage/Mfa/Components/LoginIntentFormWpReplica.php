<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components;

class LoginIntentFormWpReplica extends BaseForm {

	public const SLUG = 'render_shield_wploginreplica_form';
	public const TEMPLATE = '/components/wplogin_replica/form.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'button_cancel' => __( 'Cancel', 'wp-simple-firewall' ),
				'button_submit' => __( 'Complete Login', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'message_type' => 'info',
			],
		];
	}
}