<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\FullPage\Mfa\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;

class LoginIntentFormShield extends BaseForm {

	public const SLUG = 'render_shield_login_intent_form';
	public const TEMPLATE = '/components/login_intent/form.twig';

	protected function getRenderData() :array {
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