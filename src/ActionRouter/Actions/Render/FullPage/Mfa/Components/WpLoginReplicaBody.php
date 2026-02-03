<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components;

use FernleafSystems\Wordpress\Services\Services;

class WpLoginReplicaBody extends Base {

	public const SLUG = 'render_shield_wploginreplica_body';
	public const TEMPLATE = '/components/wplogin_replica/login_body.twig';

	protected function getRenderData() :array {
		$errorMsg = $this->action_data[ 'msg_error' ] ?? '';
		return [
			'content' => [
				'form' => self::con()->action_router->render( LoginIntentFormWpReplica::class, $this->action_data ),
			],
			'flags'   => [
				'has_error_msg'    => !empty( $errorMsg ),
				'is_interim_login' => (bool)$this->action_data[ 'interim_login' ],
			],
			'hrefs'   => [
				'home' => Services::WpGeneral()->getHomeUrl(),
			],
			'strings' => [
				'error_msg' => $errorMsg,
				'back_home' => __( 'Go Back Home', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'user_id'
		];
	}
}