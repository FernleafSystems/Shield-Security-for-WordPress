<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Render;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;

class RenderLoginIntentPage extends RenderBase {

	use Shield\Utilities\Consumer\WpUserConsumer;

	protected function buildPage() :string {
		$con = $this->getCon();
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$data = [
			'strings' => [
				'what_is_this' => __( 'What is this?', 'wp-simple-firewall' ),
				'page_title'   => sprintf( __( '%s Login Verification', 'wp-simple-firewall' ), $con->getHumanName() ),
			],
			'hrefs'   => [
				'css_bootstrap' => $con->urls->forCss( 'bootstrap' ),
				'js_bootstrap'  => $con->urls->forJs( 'bootstrap' ),
				'what_is_this'  => 'https://help.getshieldsecurity.com/article/322-what-is-the-login-authentication-portal',
			],
			'imgs'    => [
				'banner'  => $con->labels->url_img_pagebanner,
				'favicon' => $con->labels->icon_url_32x32,
			],
			'flags'   => [
				'show_branded_links' => !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled(),
			],
			'content' => [
				'form' => $this->renderForm(),
			]
		];

		// Provide the U2F scripts if required.
		$data[ 'head' ] = [
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
		];

		return $mod->renderTemplate(
			'/pages/login_intent/index.twig',
			Services::DataManipulation()->mergeArraysRecursive( $mod->getUIHandler()->getBaseDisplayData(), $data )
		);
	}

	private function renderForm() :string {
		/** @var LoginGuard\ModCon $mod */
		$mod = $this->getMod();
		$con = $this->getCon();

		$notice = $con->getAdminNotices()->getFlashNotice();
		if ( $notice instanceof NoticeVO ) {
			$msg = $notice->render_data[ 'message' ];
		}
		else {
			$msg = __( 'Please supply at least 1 authentication code', 'wp-simple-firewall' );
		}

		if ( !empty( $msg ) && !$con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled() ) {
			$msg .= sprintf( ' [<a href="%s" target="_blank">%s</a>]', 'https://shsec.io/shieldcantaccess', __( 'More Info', 'wp-simple-firewall' ) );
		}

		return $mod->renderTemplate( '/components/login_intent/form.twig',
			Services::DataManipulation()->mergeArraysRecursive(
				$mod->getUIHandler()->getBaseDisplayData(),
				$this->getCommonFormData(),
				[
					'strings' => [
						'message' => $msg,
					],
					'vars'    => [
						'message_type' => 'info',
					],
				]
			) );
	}
}