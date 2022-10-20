<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\FullPage\Mfa;

class ShieldLoginIntentPage extends Base {

	const SLUG = 'render_login_intent_shield';
	const TEMPLATE = '/pages/shield_login_intent.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		return [
			'strings' => [
				'what_is_this' => __( 'What is this?', 'wp-simple-firewall' ),
				'page_title'   => sprintf( __( '%s Login Verification', 'wp-simple-firewall' ), $con->getHumanName() ),
			],
			'head'    => [
				'scripts' => [
					[
						'src' => $con->urls->forJs( 'u2f-bundle' ),
					],
					[
						'src' => $con->urls->forJs( 'shield/login2fa' ),
					]
				],
				'styles'  => [
					[
						'href' => $con->urls->forCss( 'shield/login2fa' ),
					]
				]
			],
			'hrefs'   => [
				'css_bootstrap' => $con->urls->forCss( 'bootstrap' ),
				'js_bootstrap'  => $con->urls->forJs( 'bootstrap' ),
				'what_is_this'  => 'https://help.getshieldsecurity.com/article/322-what-is-the-login-authentication-portal',
			],
			'imgs'    => [
				'logo_banner' => $con->labels->url_img_pagebanner,
				'favicon'     => $con->labels->icon_url_32x32,
			],
			'flags'   => [
				'show_branded_links' => !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled(),
			],
			'content' => [
				'form' => $this->getCon()
							   ->getModule_Insights()
							   ->getActionRouter()
							   ->render( Components\LoginIntentFormShield::SLUG, $this->action_data ),
			]
		];
	}
}