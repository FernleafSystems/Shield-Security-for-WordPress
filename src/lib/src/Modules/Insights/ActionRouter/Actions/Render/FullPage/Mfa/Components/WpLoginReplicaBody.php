<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\FullPage\Mfa\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

class WpLoginReplicaBody extends Base {

	const SLUG = 'render_shield_wploginreplica_body';
	const TEMPLATE = '/components/wplogin_replica/login_body.twig';

	protected function getRenderData() :array {
		global $interim_login;

		/** @var LoginGuard\ModCon $mod */
		$mod = $this->primary_mod;
		$user = Services::WpUsers()->getUserById( $this->action_data[ 'user_id' ] );
		$errorMsg = $this->action_data[ 'msg_error' ] ?? '';
		return [
			'content' => [
				'form' => $this->getCon()
							   ->getModule_Insights()
							   ->getActionRouter()
							   ->render( LoginIntentFormWpReplica::SLUG, $this->action_data ),
			],
			'flags'   => [
				'has_error_msg'    => !empty( $errorMsg ),
				'is_interim_login' => (bool)$interim_login,
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