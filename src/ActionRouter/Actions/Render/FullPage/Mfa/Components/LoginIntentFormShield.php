<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
class LoginIntentFormShield extends BaseForm {

	public const SLUG = 'render_shield_login_intent_form';
	public const TEMPLATE = '/components/login_intent/form.twig';

	protected function getRenderData() :array {
		$msg = __( 'Please supply at least 1 authentication code', 'wp-simple-firewall' );
		if ( !self::con()->comps->whitelabel->isEnabled() ) {
			$msg .= sprintf(
				' [<a href="%s" target="_blank">%s</a>]',
				'https://clk.shldscrty.com/shieldwhatis2fa',
				CommonDisplayStrings::get( 'more_info_label' )
			);
		}

		return [
			'strings' => [
				'message' => $msg,
			],
			'vars'    => [
				'message_type' => 'info',
			],
		];
	}
}
