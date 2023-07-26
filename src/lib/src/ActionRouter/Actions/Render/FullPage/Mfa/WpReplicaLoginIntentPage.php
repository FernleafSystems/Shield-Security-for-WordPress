<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Mfa;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Assets\Enqueue;

class WpReplicaLoginIntentPage extends BaseLoginIntentPage {

	public const SLUG = 'render_login_intent_wploginreplica';
	public const TEMPLATE = '/components/wplogin_replica/wp_login.twig';

	protected function preExec() {
		add_filter( 'shield/custom_enqueues', function ( array $enqueues ) {
			$enqueues[ Enqueue::JS ][] = 'shield/login2fa';
			$enqueues[ Enqueue::CSS ][] = 'shield/login2fa';
			return $enqueues;
		} );
	}

	protected function getRenderData() :array {
		$con = $this->con();
		return [
			'content' => [
				'header' => $con->action_router->render( Components\WpLoginReplicaHeader::SLUG,
					\array_merge( $this->action_data, [
						'title' => __( 'Login 2FA Verification', 'wp-simple-firewall' )
					] )
				),
				'body'   => $this->action_data[ 'include_body' ] ?
					$con->action_router->render( Components\WpLoginReplicaBody::SLUG, $this->action_data ) : '',
				'footer' => $con->action_router->render( Components\WpLoginReplicaFooter::SLUG, $this->action_data ),
			]
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'user_id',
			'include_body',
		];
	}
}