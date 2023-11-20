<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;
use FernleafSystems\Wordpress\Services\Services;

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
			],
			'vars'    => [
				'inline_js' => [
					sprintf( 'var shield_vars_login_2fa = %s;', \json_encode(
						[
							'comps' => [
								'login_2fa' => Services::DataManipulation()->mergeArraysRecursive(
									[
										'vars'    => [
											'time_remaining' => $this->getLoginIntentExpiresAt() - Services::Request()
																										   ->ts(),
										],
										'strings' => [
											'seconds'       => \strtolower( __( 'Seconds', 'wp-simple-firewall' ) ),
											'minutes'       => \strtolower( __( 'Minutes', 'wp-simple-firewall' ) ),
											'login_expired' => __( 'Login Expired', 'wp-simple-firewall' ),
										],
									],
									$this->getLoginIntentJavascript()
								)
							]
						]
					) ),
				],
			]
		];
	}

	protected function getLoginIntentExpiresAt() :int {
		$mod = self::con()->getModule_LoginGuard();
		$mfaCon = $mod->getMfaController();
		/** @var Options $opts */
		$opts = $mod->opts();

		$user = Services::WpUsers()->getUserById( $this->action_data[ 'user_id' ] );

		$intentAt = $mfaCon->getActiveLoginIntents( $user )
					[ $mfaCon->findHashedNonce( $user, $this->action_data[ 'plain_login_nonce' ] ) ][ 'start' ] ?? 0;
		return Services::Request()
					   ->carbon()
					   ->setTimestamp( $intentAt )
					   ->addMinutes( $opts->getLoginIntentMinutes() )->timestamp;
	}

	protected function getScripts() :array {
		$scripts = parent::getScripts();
		$scripts[ 51 ] = [
			'src' => self::con()->urls->forDistJS( 'login_2fa' ),
			'id'  => 'shield/login_2fa',
			'footer' => true,
		];
		return $scripts;
	}

	protected function getStyles() :array {
		$styles = parent::getStyles();
		$styles[ 51 ] = [
			'href' => self::con()->urls->forDistCSS( 'login_2fa' ),
			'id'   => 'shield/login_2fa',
		];
		return $styles;
	}
}