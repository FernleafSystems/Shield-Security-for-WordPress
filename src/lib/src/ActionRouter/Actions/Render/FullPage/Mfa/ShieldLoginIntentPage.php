<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa;

class ShieldLoginIntentPage extends BaseLoginIntentPage {

	public const SLUG = 'render_login_intent_shield';
	public const TEMPLATE = '/pages/shield_login_intent.twig';

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'strings' => [
				'what_is_this' => __( 'What is this?', 'wp-simple-firewall' ),
				'page_title'   => sprintf( __( '%s Login Verification', 'wp-simple-firewall' ), $con->getHumanName() ),
			],
			'hrefs'   => [
				'what_is_this' => 'https://help.getshieldsecurity.com/article/322-what-is-the-login-authentication-portal',
			],
			'imgs'    => [
				'logo_banner' => $con->labels->url_img_pagebanner,
				'favicon'     => $con->labels->icon_url_32x32,
			],
			'flags'   => [
				'show_branded_links' => !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled(),
			],
			'content' => [
				'form' => $con->action_router->render( Components\LoginIntentFormShield::SLUG, $this->action_data ),
			]
		];
	}

	protected function getScripts() :array {
		$urlBuilder = self::con()->urls;
		$scripts = parent::getScripts();
		$scripts[ 50 ] = [
			'src' => $urlBuilder->forJs( 'u2f-bundle' ),
			'id'  => 'u2f-bundle',
		];
		$scripts[ 51 ] = [
			'src' => $urlBuilder->forJs( 'shield/login2fa' ),
			'id'  => 'shield/login2fa',
		];
		return $scripts;
	}

	protected function getStyles() :array {
		$styles = parent::getStyles();
		$styles[ 51 ] = [
			'href' => self::con()->urls->forCss( 'shield/login2fa' ),
			'id'   => 'shield/login2fa',
		];
		return $styles;
	}
}