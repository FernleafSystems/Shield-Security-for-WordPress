<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa\Components;

use FernleafSystems\Wordpress\Services\Services;

class WpLoginReplicaBody extends Base {

	public const SLUG = 'render_shield_wploginreplica_body';
	public const TEMPLATE = '/components/wplogin_replica/login_body.twig';

	protected function getRenderData() :array {
		$data = $this->loginIntentRenderData();
		return [
			'content' => [
				'form' => self::con()->action_router->render( LoginIntentFormWpReplica::class, $this->action_data ),
			],
			'flags'   => [
				'has_error_msg'    => $data[ 'msg_error' ] !== '',
				'is_interim_login' => $data[ 'interim_login' ] === '1',
			],
			'hrefs'   => [
				'home' => Services::WpGeneral()->getHomeUrl(),
			],
			'strings' => [
				'error_msg' => $data[ 'msg_error' ],
				'back_home' => __( 'Go Back Home', 'wp-simple-firewall' ),
			],
		];
	}
}
